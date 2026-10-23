<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppContact extends Model
{
    protected $table = 'whatsapp_contacts';

    protected $fillable = [
        'wa_id',
        'profile_name',
        'display_name',
        'user_id',
        'opted_in_at',
        'opt_in_source',
        'opted_out_at',
        'last_inbound_at',
        'last_message_at',
        'last_message_preview',
        'unread_count',
        'notes',
    ];

    protected $casts = [
        'opted_in_at' => 'datetime',
        'opted_out_at' => 'datetime',
        'last_inbound_at' => 'datetime',
        'last_message_at' => 'datetime',
        'unread_count' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messages()
    {
        return $this->hasMany(WhatsAppMessage::class, 'contact_id');
    }

    public function getNameLabelAttribute(): string
    {
        return $this->display_name
            ?: optional($this->user)->name
            ?: $this->profile_name
            ?: '+' . $this->wa_id;
    }

    public function getPhoneLabelAttribute(): string
    {
        return '+' . $this->wa_id;
    }

    /**
     * WhatsApp only allows free-form messages within 24 hours of the contact's last inbound message.
     */
    public function isServiceWindowOpen(): bool
    {
        return $this->last_inbound_at !== null && $this->last_inbound_at->gt(now()->subHours(24));
    }

    public function serviceWindowClosesAt()
    {
        return $this->last_inbound_at?->copy()->addHours(24);
    }

    public function isOptedIn(): bool
    {
        return $this->opted_in_at !== null && $this->opted_out_at === null;
    }

    public function isOptedOut(): bool
    {
        return $this->opted_out_at !== null;
    }
}
