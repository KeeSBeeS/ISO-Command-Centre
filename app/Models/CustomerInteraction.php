<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerInteraction extends Model
{
    protected $fillable = [
        'customer_id',
        'customer_site_id',
        'customer_contact_id',
        'type',
        'subject',
        'notes',
        'occurred_at',
        'follow_up_at',
        'created_by',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'follow_up_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function site()
    {
        return $this->belongsTo(CustomerSite::class, 'customer_site_id');
    }

    public function contact()
    {
        return $this->belongsTo(CustomerContact::class, 'customer_contact_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTypeIconAttribute(): string
    {
        return match ($this->type) {
            'call' => '📞',
            'email' => '✉️',
            'meeting' => '🗓️',
            'site_visit' => '📍',
            'task' => '✅',
            default => '📝',
        };
    }

    public function getIsFollowUpDueAttribute(): bool
    {
        return $this->follow_up_at !== null && $this->follow_up_at->isFuture() === false;
    }
}
