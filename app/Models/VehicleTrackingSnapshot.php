<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleTrackingSnapshot extends Model
{
    protected $fillable = [
        'vehicle_id',
        'provider',
        'provider_vehicle_id',
        'registration_number',
        'recorded_at',
        'latitude',
        'longitude',
        'speed',
        'odometer',
        'ignition',
        'status',
        'address',
        'raw_payload',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'speed' => 'decimal:2',
        'odometer' => 'integer',
        'ignition' => 'boolean',
        'raw_payload' => 'array',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
