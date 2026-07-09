<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceRawRecord extends Model
{
    protected $fillable = [
        'attendance_import_id',
        'user_id',
        'person_id',
        'employee_name',
        'department',
        'attendance_status',
        'attendance_check_point',
        'custom_name',
        'data_source',
        'handling_type',
        'temperature',
        'abnormal',
        'recorded_at',
        'attendance_date',
        'source_row_hash',
        'raw_payload',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'attendance_date' => 'date',
        'raw_payload' => 'array',
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
