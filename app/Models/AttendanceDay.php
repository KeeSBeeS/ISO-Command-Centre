<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AttendanceDay extends Model
{
    protected $fillable = [
        'user_id',
        'attendance_date',
        'start_time',
        'end_time',
        'first_status',
        'last_status',
        'record_count',
        'work_minutes',
        'is_late',
        'late_minutes',
        'is_public_holiday',
        'public_holiday_name',
        'attendance_import_id',
        'source_names',
        'anomalies',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'record_count' => 'integer',
        'work_minutes' => 'integer',
        'is_late' => 'boolean',
        'late_minutes' => 'integer',
        'is_public_holiday' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function import()
    {
        return $this->belongsTo(AttendanceImport::class, 'attendance_import_id');
    }

    public function records()
    {
        return $this->hasMany(AttendanceRawRecord::class, 'user_id', 'user_id')
            ->whereColumn('attendance_raw_records.attendance_date', 'attendance_days.attendance_date')
            ->orderBy('recorded_at');
    }

    public function getWorkHoursAttribute(): string
    {
        $minutes = max(0, (int) $this->work_minutes);
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return sprintf('%02d:%02d', $hours, $remaining);
    }

    public function getLateLabelAttribute(): string
    {
        if ($this->is_public_holiday) {
            return 'Public holiday';
        }

        if (!$this->is_late) {
            return 'On time';
        }

        $minutes = (int) $this->late_minutes;
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $hours > 0
            ? sprintf('Late by %dh %02dm', $hours, $remaining)
            : sprintf('Late by %d min', $remaining);
    }

    public function scopeLate($query)
    {
        return $query->where('is_late', true);
    }

    public function scopeWorkingDays($query)
    {
        if (Schema::hasColumn('attendance_days', 'is_public_holiday')) {
            return $query->where(function ($nested) {
                $nested->where('is_public_holiday', false)->orWhereNull('is_public_holiday');
            });
        }

        return $query;
    }

}
