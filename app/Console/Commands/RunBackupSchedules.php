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
                $intervalMinutes = $schedule->intervalInMinutes();
                if ($intervalMinutes > 0) {
                    $shouldRun = $last->diffInMinutes($now) >= $intervalMinutes;
                }
            }

            if ($shouldRun) {
                $this->info("Running schedule #{$schedule->id} ({$schedule->type} / {$schedule->format})");

                Artisan::call('backup:database', [
                    '--format' => $schedule->format,
                    '--type' => $schedule->type,
                ]);

                $schedule->update(['last_run_at' => $now]);
            }
        }

        return 0;
    }
}
