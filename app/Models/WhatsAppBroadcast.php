<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppBroadcast extends Model
{
    protected $table = 'whatsapp_broadcasts';

    protected $fillable = [
        'name',
        'template_name',
        'template_language',
        'template_params',
        'audience_type',
        'audience_meta',
        'status',
        'total_count',
        'created_by',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'template_params' => 'array',
        'audience_meta' => 'array',
        'total_count' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function messages()
    {
        return $this->hasMany(WhatsAppMessage::class, 'broadcast_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
