<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OvertimeEntry extends Model
{
    protected $fillable = [
        'user_id',
        'crm_client_id',
        'crm_client_site_id',
        'overtime_date',
        'start_time',
        'end_time',
        'hours',
        'is_installation',
        'is_service',
        'note',
        'status',
        'created_by',
    ];

    protected $casts = [
        'overtime_date' => 'date',
        'hours' => 'decimal:2',
        'is_installation' => 'boolean',
        'is_service' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function client()
    {
        return $this->belongsTo(CrmClient::class, 'crm_client_id');
    }

    public function site()
    {
        return $this->belongsTo(CrmClientSite::class, 'crm_client_site_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTypeLabelAttribute(): string
    {
        $types = [];
        if ($this->is_installation) {
            $types[] = 'Installation';
        }
        if ($this->is_service) {
            $types[] = 'Service';
        }

        return $types ? implode(' + ', $types) : 'General overtime';
    }

    public function getTypeIconAttribute(): string
    {
        if ($this->is_installation && $this->is_service) {
            return '🛠️';
        }
        if ($this->is_installation) {
            return '🔧';
        }
        if ($this->is_service) {
            return '🧰';
        }

        return '⏱️';
    }

    public function getSiteLabelAttribute(): string
    {
        $client = optional($this->client)->name;
        $site = optional($this->site)->name;

        return trim(($client ?: 'Unknown client') . ($site ? ' · ' . $site : ''));
    }
}
