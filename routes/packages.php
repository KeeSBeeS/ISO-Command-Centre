<?php

use App\Http\Controllers\PackageManagerController;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\ForcePasswordChange;
use Illuminate\Support\Facades\Route;

// Package Manager routes. Loaded from the end of routes/web.php so web.php itself
// never has to be replaced to add or update them. web.php is already inside the
// 'web' middleware group, so only the app's own middleware is applied here.
Route::middleware([EnsureInstalled::class, 'auth', ForcePasswordChange::class])->group(function () {
    Route::get('/settings/packages', [PackageManagerController::class, 'index'])
        ->middleware(CheckPermission::class . ':packages.manage')
        ->name('packages.index');
    Route::post('/settings/packages/upload', [PackageManagerController::class, 'upload'])
        ->middleware(CheckPermission::class . ':packages.manage')
        ->name('packages.upload');
    Route::post('/settings/packages/scan', [PackageManagerController::class, 'scan'])
        ->middleware(CheckPermission::class . ':packages.manage')
        ->name('packages.scan');
    Route::get('/settings/packages/uploads/{upload}', [PackageManagerController::class, 'show'])
        ->middleware(CheckPermission::class . ':packages.manage')
        ->name('packages.show');
    Route::post('/settings/packages/uploads/{upload}/install', [PackageManagerController::class, 'install'])
        ->middleware(CheckPermission::class . ':packages.manage')
        ->name('packages.install');
    Route::delete('/settings/packages/uploads/{upload}', [PackageManagerController::class, 'destroy'])
        ->middleware(CheckPermission::class . ':packages.manage')
        ->name('packages.destroy');
    Route::get('/settings/packages/installs/{install}', [PackageManagerController::class, 'history'])
        ->middleware(CheckPermission::class . ':packages.manage')
        ->name('packages.history');
    Route::post('/settings/packages/installs/{install}/rollback', [PackageManagerController::class, 'rollback'])
        ->middleware(CheckPermission::class . ':packages.manage')
        ->name('packages.rollback');
    Route::get('/settings/packages/installs/{install}/backup', [PackageManagerController::class, 'downloadBackup'])
        ->middleware(CheckPermission::class . ':packages.manage')
        ->name('packages.backup');
});
