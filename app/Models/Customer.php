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
        'customer_type',
        'industry',
        'contact_person',
        'email',
        'phone',
        'website',
        'address',
        'status',
        'account_manager_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'created_by' => 'integer',
        'account_manager_id' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function accountManager()
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function sites()
    {
        return $this->hasMany(CustomerSite::class);
    }

    public function contacts()
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function companyContacts()
    {
        return $this->hasMany(CustomerContact::class)->whereNull('customer_site_id');
    }

    public function interactions()
    {
        return $this->hasMany(CustomerInteraction::class)->orderByDesc('occurred_at');
    }

    public function getTypeIconAttribute(): string
    {
        return match ($this->customer_type) {
            'prospect' => '🧲',
            'supplier' => '📦',
            'partner' => '🤝',
            'other' => '🏷️',
            default => '🏢',
        };
    }
}
