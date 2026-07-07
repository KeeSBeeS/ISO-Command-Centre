<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleServiceRecord extends Model
{
    protected $fillable = [
        'vehicle_id',
        'recorded_by',
        'service_date',
        'service_odo',
        'next_service_odo_snapshot',
        'notes',
    ];

    protected $casts = [
        'service_date' => 'date',
        'service_odo' => 'integer',
        'next_service_odo_snapshot' => 'integer',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
