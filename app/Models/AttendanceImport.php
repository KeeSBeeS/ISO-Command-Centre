<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceImport extends Model
{
    protected $fillable = [
        'source',
        'source_identifier',
        'filename',
        'received_from',
        'received_subject',
        'received_at',
        'imported_by',
        'raw_rows',
        'matched_rows',
        'skipped_rows',
        'day_rows',
        'status',
        'notes',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'raw_rows' => 'integer',
        'matched_rows' => 'integer',
        'skipped_rows' => 'integer',
        'day_rows' => 'integer',
    ];

    public function importer()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function records()
    {
        return $this->hasMany(AttendanceRawRecord::class, 'attendance_import_id');
    }
}
