<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleFuelUp extends Model
{
    protected $fillable = [
        'vehicle_id',
        'uploaded_by',
        'source',
        'car_name',
        'model_name',
        'km_per_litre',
        'odometer',
        'km',
        'litres',
        'price_per_litre',
        'total_cost',
        'city_percentage',
        'fuelup_date',
        'date_added',
        'tags',
        'notes',
        'missed_fuelup',
        'partial_fuelup',
        'latitude',
        'longitude',
        'brand',
        'source_row_hash',
    ];

    protected $casts = [
        'km_per_litre' => 'decimal:2',
        'odometer' => 'integer',
        'km' => 'decimal:2',
        'litres' => 'decimal:2',
        'price_per_litre' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'city_percentage' => 'decimal:2',
        'fuelup_date' => 'date',
        'date_added' => 'datetime',
        'missed_fuelup' => 'boolean',
        'partial_fuelup' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
