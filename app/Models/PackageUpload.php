<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageUpload extends Model
{
    protected $fillable = [
        'original_name',
        'stored_name',
        'sha256',
        'size',
        'version',
        'title',
        'manifest',
        'status',
        'validation_error',
        'source',
        'uploaded_by',
    ];

    protected $casts = [
        'manifest' => 'array',
        'size' => 'integer',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function installs()
    {
        return $this->hasMany(PackageInstall::class);
    }
}
