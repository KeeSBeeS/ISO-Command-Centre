<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceRawRecord extends Model
{
    protected $fillable = [
        'attendance_import_id',
        'user_id',
        'employee_name',
        'attendance_status',
        'recorded_at',
        'attendance_date',
        'source_row_hash',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'attendance_date' => 'date',
    ];

    public function import()
    {
        return $this->belongsTo(AttendanceImport::class, 'attendance_import_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
