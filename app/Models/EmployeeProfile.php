<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeProfile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'employee_number',
        'job_title',
        'phone',
        'mobile',
        'emergency_contact',
        'notes',
        'started_at',
        'status',
    ];

    protected $casts = [
        'started_at' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
