<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'is_deductible',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_deductible' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
