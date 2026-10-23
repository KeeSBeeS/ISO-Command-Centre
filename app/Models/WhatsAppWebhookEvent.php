<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppWebhookEvent extends Model
{
    protected $table = 'whatsapp_webhook_events';

    protected $fillable = [
        'signature_valid',
        'payload',
        'processed_at',
        'error',
    ];

    protected $casts = [
        'signature_valid' => 'boolean',
        'processed_at' => 'datetime',
    ];
}
