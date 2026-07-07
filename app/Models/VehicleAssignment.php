<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleAssignment extends Model
{
    protected $fillable = [
        'vehicle_id',
        'user_id',
        'assigned_by',
        'assigned_at',
        'unassigned_at',
        'status',
        'policy_warning',
        'notes',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'unassigned_at' => 'datetime',
        'policy_warning' => 'boolean',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
