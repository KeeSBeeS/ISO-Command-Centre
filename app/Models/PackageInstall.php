<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageInstall extends Model
{
    protected $fillable = [
        'package_upload_id',
        'install_key',
        'package',
        'version',
        'previous_version',
        'title',
        'status',
        'manifest',
        'package_sha256',
        'files_added',
        'files_replaced',
        'files_deleted',
        'migrations_run',
        'log',
        'error',
        'installed_by',
        'installed_at',
        'rolled_back_by',
        'rolled_back_at',
    ];

    protected $casts = [
        'manifest' => 'array',
        'files_added' => 'integer',
        'files_replaced' => 'integer',
        'files_deleted' => 'integer',
        'migrations_run' => 'integer',
        'installed_at' => 'datetime',
        'rolled_back_at' => 'datetime',
    ];

    public function upload()
    {
        return $this->belongsTo(PackageUpload::class, 'package_upload_id');
    }

    public function installer()
    {
        return $this->belongsTo(User::class, 'installed_by');
    }

    public function rolledBackBy()
    {
        return $this->belongsTo(User::class, 'rolled_back_by');
    }
}
