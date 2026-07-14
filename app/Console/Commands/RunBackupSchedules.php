<?php

namespace App\Console\Commands;

use App\Models\BackupSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RunBackupSchedules extends Command
{
    protected $signature = 'backup:run-schedules';
    protected $description = 'Run enabled backup schedules based on their intervals';

    public function handle(): int
    {
        $schedules = BackupSchedule::where('enabled', true)->get();
        $now = now();

        foreach ($schedules as $schedule) {
            $shouldRun = false;

            if (!$schedule->last_run_at) {
                $shouldRun = true;
            } else {
                $last = $schedule->last_run_at;
                switch ($schedule->interval) {
                    case 'hourly':
                        $shouldRun = $last->diffInHours($now) >= 1;
                        break;
                    case 'daily':
                        $shouldRun = $last->diffInDays($now) >= 1;
                        break;
                    case 'weekly':
                        $shouldRun = $last->diffInWeeks($now) >= 1;
                        break;
                    case 'monthly':
                        $shouldRun = $last->diffInMonths($now) >= 1;
                        break;
                }
            }

            if ($shouldRun) {
                $this->info("Running schedule #{$schedule->id} ({$schedule->type} / {$schedule->format})");

                if ($schedule->type === 'full') {
                    Artisan::call('backup:database', ['--format' => $schedule->format]);
                } else {
                    // For single-table exports, we just run the full backup command for now
                    // Or we could implement a specific table export command
                    Artisan::call('backup:database', ['--format' => $schedule->format]);
                }

                $schedule->update(['last_run_at' => $now]);
            }
        }

        return 0;
    }
}
