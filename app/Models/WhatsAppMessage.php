<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    public const STATUS_RANK = [
        'queued' => 0,
        'sending' => 1,
        'sent' => 2,
        'delivered' => 3,
        'read' => 4,
    ];

    public const MEDIA_TYPES = ['image', 'document', 'audio', 'video', 'sticker'];

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'contact_id',
        'broadcast_id',
        'direction',
        'wamid',
        'type',
        'body',
        'template_name',
        'template_language',
        'template_params',
        'media_id',
        'media_mime',
        'media_filename',
        'media_path',
        'context_wamid',
        'status',
        'error_code',
        'error_message',
        'source',
        'notification_key',
        'attempts',
        'sent_by',
        'payload',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
    ];

    protected $casts = [
        'template_params' => 'array',
        'payload' => 'array',
        'attempts' => 'integer',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function contact()
    {
        return $this->belongsTo(WhatsAppContact::class, 'contact_id');
    }

    public function broadcast()
    {
        return $this->belongsTo(WhatsAppBroadcast::class, 'broadcast_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    public function hasMedia(): bool
    {
        return in_array($this->type, self::MEDIA_TYPES, true) && ($this->media_id || $this->media_path);
    }

    public function getPreviewAttribute(): string
    {
        return match (true) {
            $this->type === 'template' => '📋 ' . $this->template_name,
            $this->type === 'image' => '📷 ' . ($this->body ?: 'Photo'),
            $this->type === 'document' => '📄 ' . ($this->body ?: $this->media_filename ?: 'Document'),
            $this->type === 'audio' => '🎤 Voice / audio',
            $this->type === 'video' => '🎬 ' . ($this->body ?: 'Video'),
            $this->type === 'sticker' => '🙂 Sticker',
            $this->type === 'location' => '📍 ' . ($this->body ?: 'Location'),
            default => (string) $this->body,
        };
    }

    public function getStatusIconAttribute(): string
    {
        return match ($this->status) {
            'queued', 'sending' => '🕓',
            'sent' => '✓',
            'delivered' => '✓✓',
            'read' => '✓✓ read',
            'failed' => '⚠ failed',
            default => '',
        };
    }
}
