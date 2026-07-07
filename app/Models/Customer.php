<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_name',
        'customer_code',
        'contact_person',
        'email',
        'phone',
        'address',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'created_by' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
