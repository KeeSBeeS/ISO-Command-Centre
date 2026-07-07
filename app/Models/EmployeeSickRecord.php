<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeSickRecord extends Model
{
    public const LEAVE_TYPES = [
        'sick' => 'Sick Leave',
        'unpaid' => 'Unpaid Leave',
        'normal' => 'Normal Leave',
        'family_responsibility' => 'Family Responsibility Leave',
    ];

    protected $fillable = [
        'user_id',
        'marked_by',
        'sick_from',
        'sick_to',
        'leave_type',
        'status',
        'sick_note_required',
        'leave_form_required',
        'notes',
        'cleared_at',
        'converted_from_type',
        'converted_by',
        'converted_at',
        'original_to',
        'extended_by',
        'extended_at',
        'removal_reason',
        'removed_at',
        'removed_by',
    ];

    protected $casts = [
        'sick_from' => 'date',
        'sick_to' => 'date',
        'original_to' => 'date',
        'sick_note_required' => 'boolean',
        'leave_form_required' => 'boolean',
        'cleared_at' => 'datetime',
        'converted_at' => 'datetime',
        'extended_at' => 'datetime',
        'removed_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function marker()
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function convertedBy()
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    public function extendedBy()
    {
        return $this->belongsTo(User::class, 'extended_by');
    }

    public function removedBy()
    {
        return $this->belongsTo(User::class, 'removed_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function getLeaveTypeSafeAttribute(): string
    {
        return $this->leave_type ?: 'sick';
    }

    public function getLeaveTypeLabelAttribute(): string
    {
        return self::LEAVE_TYPES[$this->leave_type_safe] ?? ucfirst(str_replace('_', ' ', (string) $this->leave_type_safe));
    }

    public function getLeaveIconAttribute(): string
    {
        return match ($this->leave_type_safe) {
            'sick' => '🤒',
            'unpaid' => '💸',
            'normal' => '🌴',
            'family_responsibility' => '👨‍👩‍👧',
            default => '📅',
        };
    }

    public function getDateRangeLabelAttribute(): string
    {
        $from = optional($this->sick_from)->format('Y-m-d') ?: 'Unknown';
        $to = optional($this->sick_to)->format('Y-m-d') ?: $from;
        return $from === $to ? $from : $from . ' to ' . $to;
    }

    public function getStatusLabelAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', (string) $this->status));
    }
}
