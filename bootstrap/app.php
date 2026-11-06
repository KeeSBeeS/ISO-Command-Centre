<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // During an update the site is in maintenance mode. Only the way to
        // finish it gets through: the finish step, the Updates page (which
        // finishes it once the new version runs) and logging in.
        $middleware->preventRequestsDuringMaintenance(except: ['settings/updates', 'settings/updates/finish/*', 'login']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

// On the server, each release lives in its own folder and is started by the
// front door (or cron.php), which defines ISO_HOME. Settings and storage
// live in ISO_HOME/shared so they survive updates and rollbacks.
if (defined('ISO_HOME')) {
    $app->useEnvironmentPath(ISO_HOME.'/shared');
    $app->useStoragePath(ISO_HOME.'/shared/storage');
}

return $app;
