<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerContact extends Model
{
    protected $fillable = [
        'customer_id',
        'customer_site_id',
        'name',
        'position',
        'contact_type',
        'email',
        'phone',
        'mobile',
        'is_primary',
        'status',
        'notes',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function site()
    {
        return $this->belongsTo(CustomerSite::class, 'customer_site_id');
    }

    public function getRoleIconAttribute(): string
    {
        return match (strtolower((string) $this->contact_type)) {
            'engineer' => '🧑‍🔧',
            'foreman' => '👷',
            'stores', 'stock controller' => '📦',
            'accounts' => '🧾',
            'buyer', 'procurement' => '🛒',
            'maintenance manager' => '🛠️',
            'safety officer' => '🦺',
            'site manager', 'plant manager', 'operations manager' => '🏭',
            default => '👤',
        };
    }
}
