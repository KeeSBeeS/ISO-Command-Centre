<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'total_days',
        'status',
        'is_deductible',
        'reason',
        'manager_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_days' => 'decimal:2',
        'is_deductible' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getDateRangeLabelAttribute(): string
    {
        $start = optional($this->start_date)->format('Y-m-d') ?: '-';
        $end = optional($this->end_date)->format('Y-m-d') ?: '-';
        return $start === $end ? $start : $start . ' to ' . $end;
    }

    public function getStatusLabelAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function scopeVisibleTo($query, User $user)
    {
        if ($user->hasPermission('leave.manage')) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }
}
