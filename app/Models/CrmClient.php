<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmClient extends Model
{
    protected $fillable = [
        'name',
        'client_code',
        'client_type',
        'industry',
        'status',
        'account_manager_id',
        'distance_from_office_km',
        'phone',
        'email',
        'website',
        'address',
        'notes',
    ];

    protected $casts = [
        'distance_from_office_km' => 'decimal:2',
    ];

    public function accountManager()
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function sites()
    {
        return $this->hasMany(CrmClientSite::class, 'crm_client_id');
    }

    public function contacts()
    {
        return $this->hasMany(CrmClientContact::class, 'crm_client_id');
    }

    public function clientContacts()
    {
        return $this->hasMany(CrmClientContact::class, 'crm_client_id')->whereNull('crm_client_site_id');
    }

    public function getDisplayDistanceAttribute(): string
    {
        $siteDistances = $this->relationLoaded('sites')
            ? $this->sites->pluck('distance_from_office_km')->filter(fn ($value) => $value !== null && $value !== '')
            : collect();

        if ($siteDistances->count() > 0) {
            return number_format((float) $siteDistances->min(), 1) . ' km nearest site';
        }

        if ($this->distance_from_office_km === null || $this->distance_from_office_km === '') {
            return 'Use site distance';
        }

        return number_format((float) $this->distance_from_office_km, 1) . ' km';
    }

    public function getStatusIconAttribute(): string
    {
        return match ($this->status) {
            'active' => '✅',
            'prospect' => '🧲',
            'inactive' => '⛔',
            default => '🏢',
        };
    }
}
