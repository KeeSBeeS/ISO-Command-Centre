<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeLeaveAllocation extends Model
{
    protected $fillable = [
        'user_id',
        'year',
        'allocated_days',
        'carried_over_days',
        'notes',
        'allocated_by',
    ];

    protected $casts = [
        'year' => 'integer',
        'allocated_days' => 'decimal:2',
        'carried_over_days' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function allocator()
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }

    public function getTotalDaysAttribute(): float
    {
        return (float) $this->allocated_days + (float) $this->carried_over_days;
    }
}
