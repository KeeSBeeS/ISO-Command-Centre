<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleDocument extends Model
{
    public const TYPES = [
        'natis' => 'NATIS Document',
        'license_disk' => 'License Disk',
        'insurance' => 'Insurance',
        'service_record' => 'Service Record',
        'other' => 'Other',
    ];

    protected $fillable = [
        'vehicle_id',
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

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
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

    public function scopeReminderDue($query)
    {
        return $query->where('status', 'active')
            ->where('has_expiry', true)
            ->whereNotNull('expires_at')
            ->whereNotNull('reminder_date')
            ->whereDate('reminder_date', '<=', now()->toDateString());
    }
}
