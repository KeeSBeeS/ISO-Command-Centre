<?php

namespace App\Providers;

use App\Support\EnvFile;
use App\Updates\DatabaseBackup;
use App\Updates\GitHubReleases;
use App\Updates\Server;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EnvFile::class, fn ($app) => new EnvFile($app->environmentFilePath()));

        // Read from config each time, so tests can point them at temporary folders.
        $this->app->bind(Server::class, fn () => new Server(config('iso.home'), config('iso.web')));
        $this->app->bind(GitHubReleases::class, fn () => new GitHubReleases(
            config('iso.updates.api_url'), config('iso.updates.repository'), config('iso.updates.token'),
        ));
        $this->app->bind(DatabaseBackup::class, function ($app) {
            $server = $app->make(Server::class);

            return new DatabaseBackup(DB::connection(), $server->available() ? $server->home('backups') : storage_path('app/backups'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
