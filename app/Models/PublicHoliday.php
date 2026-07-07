<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicHoliday extends Model
{
    protected $fillable = [
        'holiday_date',
        'name',
        'country_code',
        'is_company_closed',
        'notes',
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'is_company_closed' => 'boolean',
    ];

    public function getLabelAttribute(): string
    {
        return $this->name . ' (' . optional($this->holiday_date)->format('Y-m-d') . ')';
    }
}
