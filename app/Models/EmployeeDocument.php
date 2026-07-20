<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeDocument extends Model
{
    public const TYPES = [
        'medical' => 'Medical',
        'sick_note' => 'Sick Note',
        'warning' => 'Warning',
        'certificate' => 'Certificate',
        'company_policy' => 'Company Policy',
        'vehicle_policy' => 'Vehicle Policy',
        'other' => 'Other',
    ];

    protected $fillable = [
        'user_id',
        'uploaded_by',
        'document_type',
        'title',
        'file_path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'has_expiry',
        'expires_at',
        'remind_days_before',
        'reminder_date',
        'last_reminder_sent_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'has_expiry' => 'boolean',
        'expires_at' => 'date',
        'reminder_date' => 'date',
        'last_reminder_sent_at' => 'datetime',
        'size_bytes' => 'integer',
        'remind_days_before' => 'integer',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->document_type] ?? ucfirst(str_replace('_', ' ', (string) $this->document_type));
    }

    public function getFileSizeLabelAttribute(): string
    {
        $bytes = (int) $this->size_bytes;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }

    public function getExpiryStateAttribute(): string
    {
        if (!$this->has_expiry || !$this->expires_at) {
            return 'no-expiry';
        }

        if ($this->status !== 'active') {
            return 'inactive';
        }

        if ($this->expires_at->isPast() && !$this->expires_at->isToday()) {
            return 'expired';
        }

        if ($this->reminder_date && $this->reminder_date->lte(now()->toDateString())) {
            return 'reminder-due';
        }

        return 'valid';
    }

    public function getExpiryBadgeClassAttribute(): string
    {
        return match ($this->expiry_state) {
            'expired' => 'danger',
            'reminder-due' => 'warn',
            'inactive', 'no-expiry' => 'off',
            default => '',
        };
    }

    public function getExpirySummaryAttribute(): string
    {
        if (!$this->has_expiry || !$this->expires_at) {
            return 'No expiry';
        }

        if ($this->status !== 'active') {
            return 'Inactive';
        }

        $days = (int) now()->startOfDay()->diffInDays($this->expires_at->copy()->startOfDay(), false);

        if ($days < 0) {
            return 'Expired ' . abs($days) . ' day' . (abs($days) === 1 ? '' : 's') . ' ago';
        }

        if ($days === 0) {
            return 'Expires today';
        }

        return 'Expires in ' . $days . ' day' . ($days === 1 ? '' : 's');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeReminderDue($query)
    {
        return $query->where('status', 'active')
            ->where('has_expiry', true)
            ->whereNotNull('expires_at')
            ->whereNotNull('reminder_date')
            ->whereDate('reminder_date', '<=', now()->toDateString());
    }
}
