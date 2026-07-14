<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BackupSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'format',
        'interval',
        'last_run_at',
        'enabled',
    ];

    protected $casts = [
        'last_run_at' => 'datetime',
        'enabled' => 'boolean',
    ];

    public function nextRunAt(): \Carbon\Carbon
    {
        $now = now();
        if (!$this->last_run_at) {
            return $now;
        }

        $last = $this->last_run_at;
        return match ($this->interval) {
            'hourly'  => $last->copy()->addHour(),
            'daily'   => $last->copy()->addDay(),
            'weekly'  => $last->copy()->addWeek(),
            'monthly' => $last->copy()->addMonth(),
            default   => $last->copy()->addDay(),
        };
    }
}
