<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = [
        'make',
        'model',
        'year_model',
        'colour',
        'tracking_company_name',
        'tracking_company_contact',
        'tracking_device_number',
        'tracking_notes',
        'tracking_provider',
        'cartrack_vehicle_id',
        'cartrack_registration',
        'cartrack_external_key',
        'tracking_last_sync_at',
        'tracking_last_status',
        'tracking_last_latitude',
        'tracking_last_longitude',
        'tracking_last_address',
        'tracking_last_speed',
        'tracking_last_ignition',
        'tracking_last_odometer',
        'tracking_raw_payload',
        'odo',
        'service_interval_km',
        'service_reminder_km',
        'registration_number',
        'vehicle_key',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'year_model' => 'integer',
        'odo' => 'integer',
        'service_interval_km' => 'integer',
        'service_reminder_km' => 'integer',
        'tracking_last_sync_at' => 'datetime',
        'tracking_last_latitude' => 'decimal:7',
        'tracking_last_longitude' => 'decimal:7',
        'tracking_last_speed' => 'decimal:2',
        'tracking_last_ignition' => 'boolean',
        'tracking_last_odometer' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments()
    {
        return $this->hasMany(VehicleAssignment::class)->latest('assigned_at');
    }

    public function currentAssignment()
    {
        return $this->hasOne(VehicleAssignment::class)->where('status', 'active')->latestOfMany('assigned_at');
    }

    public function currentDriver()
    {
        return $this->hasOneThrough(
            User::class,
            VehicleAssignment::class,
            'vehicle_id',
            'id',
            'id',
            'user_id'
        )->where('vehicle_assignments.status', 'active');
    }

    public function fuelUps()
    {
        return $this->hasMany(VehicleFuelUp::class)->latest('fuelup_date');
    }

    public function latestFuelUp()
    {
        return $this->hasOne(VehicleFuelUp::class)->latestOfMany('fuelup_date');
    }

    public function documents()
    {
        return $this->hasMany(VehicleDocument::class)->latest('created_at');
    }

    public function serviceRecords()
    {
        return $this->hasMany(VehicleServiceRecord::class)->latest('service_date');
    }

    public function trackingSnapshots()
    {
        return $this->hasMany(VehicleTrackingSnapshot::class)->latest('recorded_at');
    }

    public function latestTrackingSnapshot()
    {
        return $this->hasOne(VehicleTrackingSnapshot::class)->latestOfMany('recorded_at');
    }

    public function latestServiceRecord()
    {
        return $this->hasOne(VehicleServiceRecord::class)->latestOfMany('service_date');
    }

    public function getDisplayNameAttribute(): string
    {
        $parts = array_filter([
            $this->year_model ?: null,
            $this->make ?: null,
            $this->model ?: null,
        ]);
        $name = trim(implode(' ', $parts));
        return $name ?: 'Vehicle #' . $this->id;
    }

    public function getLatestOdometerAttribute(): ?int
    {
        $latestFuelOdo = $this->fuelUps()->whereNotNull('odometer')->max('odometer');
        return $latestFuelOdo ? (int) $latestFuelOdo : ($this->odo ? (int) $this->odo : null);
    }

    public function getServiceSummaryAttribute(): array
    {
        $interval = (int) ($this->service_interval_km ?? 0);
        $reminder = (int) ($this->service_reminder_km ?? 0);
        $currentOdo = $this->latest_odometer;

        if ($interval <= 0) {
            return [
                'state' => 'not-configured',
                'label' => 'Service interval not configured',
                'current_odo' => $currentOdo,
                'last_service_odo' => null,
                'next_service_odo' => null,
                'km_remaining' => null,
                'interval_km' => $interval,
                'reminder_km' => $reminder,
                'latest_service' => null,
            ];
        }

        $latestService = $this->serviceRecords()
            ->whereNotNull('service_odo')
            ->orderByDesc('service_odo')
            ->orderByDesc('service_date')
            ->first();

        if (!$latestService) {
            return [
                'state' => 'no-baseline',
                'label' => 'No service record captured',
                'current_odo' => $currentOdo,
                'last_service_odo' => null,
                'next_service_odo' => null,
                'km_remaining' => null,
                'interval_km' => $interval,
                'reminder_km' => $reminder,
                'latest_service' => null,
            ];
        }

        $nextServiceOdo = (int) $latestService->service_odo + $interval;
        $kmRemaining = $currentOdo !== null ? $nextServiceOdo - (int) $currentOdo : null;
        $state = 'ok';
        $label = 'Service OK';

        if ($kmRemaining !== null && $kmRemaining <= 0) {
            $state = 'overdue';
            $label = 'Service overdue';
        } elseif ($kmRemaining !== null && $kmRemaining <= $reminder) {
            $state = 'due-soon';
            $label = 'Service due soon';
        }

        return [
            'state' => $state,
            'label' => $label,
            'current_odo' => $currentOdo,
            'last_service_odo' => (int) $latestService->service_odo,
            'next_service_odo' => $nextServiceOdo,
            'km_remaining' => $kmRemaining,
            'interval_km' => $interval,
            'reminder_km' => $reminder,
            'latest_service' => $latestService,
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
