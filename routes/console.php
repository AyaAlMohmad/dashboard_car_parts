<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Automatic backup every day at 2:00 AM
Schedule::command('backup:database')->dailyAt('02:00');

// Check and run user-defined backup schedules every minute
Schedule::command('backup:run-schedules')->everyMinute();

Artisan::command('reconcile:customers', function () {
    DB::transaction(function () {
        App\Models\Customer::all()->each(function ($customer) {
            $balance = 0.00;
            $open = collect();

            $events = $customer->invoices()->orderBy('created_at')->orderBy('id')->get()->map(function ($inv) {
                return ['type' => 'invoice', 'obj' => $inv, 'date' => $inv->created_at, 'id' => $inv->id];
            })->merge($customer->payments()->orderBy('created_at')->orderBy('id')->get()->map(function ($p) {
                return ['type' => 'payment', 'obj' => $p, 'date' => $p->created_at, 'id' => $p->id];
            }))->sortBy(function ($e) {
                return [$e['date'], $e['id']];
            })->values();

            foreach ($events as $e) {
                if ($e['type'] === 'invoice') {
                    $inv = $e['obj'];
                    $total = (float) $inv->total;
                    $cash = (float) $inv->paid;
                    $oldCredit = max(0, $balance);
                    $credit = min($oldCredit, $total);
                    $remaining = round(max(0, $total - $cash - $credit), 2);
                    $status = $remaining > 0 ? 'عليه دين' : 'مسدد';
                    $inv->update(['paid' => $cash, 'credit_used' => $credit, 'remaining' => $remaining, 'status' => $status]);
                    $open->push($inv);
                    $balance = round($balance + $cash - $total, 2);
                } else {
                    $amount = (float) $e['obj']->amount;
                    $balance = round($balance + $amount, 2);
                    $left = $amount;
                    foreach ($open->keys() as $k) {
                        if ($left <= 0) break;
                        $inv = $open[$k];
                        $needed = (float) $inv->remaining;
                        if ($needed <= 0) {
                            $open->forget($k);
                            continue;
                        }
                        $app = min($left, $needed);
                        $newPaid = round((float) $inv->paid + $app, 2);
                        $newRem = round($needed - $app, 2);
                        $newStatus = $newRem > 0 ? 'عليه دين' : 'مسدد';
                        $inv->update(['paid' => $newPaid, 'remaining' => $newRem, 'status' => $newStatus]);
                        $left = round($left - $app, 2);
                        if ($newRem <= 0) $open->forget($k);
                    }
                }
            }

            $customer->update([
                'balance' => $balance,
                'status' => $balance < 0 ? 'مدين' : ($balance > 0 ? 'دائن' : 'متوان'),
            ]);
        });
    });
    $this->info('Customer balances reconciled.');
})->purpose('Recalculate customer invoice credit_used, remaining and paid from history');
