<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardWidgetPreference extends Model
{
    protected $fillable = [
        'user_id',
        'widget_key',
        'sort_order',
        'size',
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
