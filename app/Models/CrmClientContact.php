<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmClientContact extends Model
{
    protected $fillable = [
        'crm_client_id',
        'crm_client_site_id',
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

    public function client()
    {
        return $this->belongsTo(CrmClient::class, 'crm_client_id');
    }

    public function site()
    {
        return $this->belongsTo(CrmClientSite::class, 'crm_client_site_id');
    }

    public function getRoleIconAttribute(): string
    {
        return match (strtolower((string) $this->contact_type)) {
            'engineer' => '🧑‍🔧',
            'foreman' => '👷',
            'stock controller' => '📦',
            'accounts' => '🧾',
            'buyer', 'procurement' => '🛒',
            'maintenance manager' => '🛠️',
            'safety officer' => '🦺',
            'site manager', 'plant manager', 'operations manager' => '🏭',
            default => '👤',
        };
    }
}
