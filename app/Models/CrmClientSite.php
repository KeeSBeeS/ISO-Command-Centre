<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmClientSite extends Model
{
    protected $fillable = [
        'crm_client_id',
        'name',
        'site_code',
        'status',
        'location',
        'distance_from_office_km',
        'maps_distance_minutes',
        'maps_distance_last_checked_at',
        'google_place_id',
        'latitude',
        'longitude',
        'notes',
    ];

    protected $casts = [
        'distance_from_office_km' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'maps_distance_last_checked_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(CrmClient::class, 'crm_client_id');
    }

    public function contacts()
    {
        return $this->hasMany(CrmClientContact::class, 'crm_client_site_id');
    }

    public function getDisplayDistanceAttribute(): string
    {
        if ($this->distance_from_office_km === null || $this->distance_from_office_km === '') {
            return 'Not calculated';
        }

        $label = number_format((float) $this->distance_from_office_km, 1) . ' km';
        if ($this->maps_distance_minutes) {
            $label .= ' / ±' . (int) $this->maps_distance_minutes . ' min';
        }
        return $label;
    }
}
