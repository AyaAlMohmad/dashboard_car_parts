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
        'interval_days',
        'interval_hours',
        'interval_minutes',
        'last_run_at',
        'enabled',
    ];

    protected $casts = [
        'last_run_at' => 'datetime',
        'enabled' => 'boolean',
        'interval_days' => 'integer',
        'interval_hours' => 'integer',
        'interval_minutes' => 'integer',
    ];

    protected $appends = ['interval'];

    public function intervalInMinutes(): int
    {
        return ($this->interval_days * 24 * 60)
             + ($this->interval_hours * 60)
             + $this->interval_minutes;
    }

    public function intervalLabel(): string
    {
        $parts = [];
        if ($this->interval_days > 0) $parts[] = $this->interval_days . ' يوم';
        if ($this->interval_hours > 0) $parts[] = $this->interval_hours . ' ساعة';
        if ($this->interval_minutes > 0) $parts[] = $this->interval_minutes . ' دقيقة';
        return $parts ? implode(' و', $parts) : 'بدون فترة';
    }

    public function getIntervalAttribute(): ?string
    {
        $d = $this->interval_days;
        $h = $this->interval_hours;
        $m = $this->interval_minutes;
        if ($d === 0 && $h === 1 && $m === 0) return 'hourly';
        if ($d === 1 && $h === 0 && $m === 0) return 'daily';
        if ($d === 7 && $h === 0 && $m === 0) return 'weekly';
        if ($d === 30 && $h === 0 && $m === 0) return 'monthly';
        return null;
    }

    public function nextRunAt(): \Carbon\Carbon
    {
        $now = now();
        if (!$this->last_run_at) {
            return $now;
        }

        $minutes = $this->intervalInMinutes();
        if ($minutes <= 0) {
            return $now;
        }

        return $this->last_run_at->copy()->addMinutes($minutes);
    }
}
