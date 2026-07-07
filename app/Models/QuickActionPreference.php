<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuickActionPreference extends Model
{
    protected $fillable = [
        'user_id',
        'action_key',
        'sort_order',
        'is_visible',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_visible' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
