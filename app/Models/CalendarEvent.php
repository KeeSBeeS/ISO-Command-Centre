<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarEvent extends Model
{
    public const TYPES = [
        'general' => 'General',
        'attendance' => 'Attendance',
        'employee_document' => 'Employee Document',
        'vehicle_document' => 'Vehicle Document',
        'vehicle_service' => 'Vehicle Service',
        'sick_leave' => 'Sick Leave',
        'public_holiday' => 'Public Holiday',
    ];

    protected $fillable = [
        'title',
        'event_type',
        'starts_at',
        'ends_at',
        'all_day',
        'source_type',
        'source_id',
        'status',
        'color',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'all_day' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->event_type] ?? ucfirst(str_replace('_', ' ', (string) $this->event_type));
    }
}
