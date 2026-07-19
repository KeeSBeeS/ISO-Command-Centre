<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerSite extends Model
{
    protected $fillable = [
        'customer_id',
        'name',
        'site_code',
        'status',
        'location',
        'notes',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function contacts()
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function interactions()
    {
        return $this->hasMany(CustomerInteraction::class)->orderByDesc('occurred_at');
    }
}
