<?php

use App\Updates\UpdateCheck;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('iso:check-updates', function (UpdateCheck $check) {
    $result = $check->run();
    $this->line($result['error'] ?? 'Latest release: '.($result['release']['version'] ?? 'none'));
})->purpose('Look for a newer ISO Command Centre release on GitHub');

// Run every minute by ISO_HOME/cron.php (a DirectAdmin cron job).
Schedule::command('iso:check-updates')->hourly();
