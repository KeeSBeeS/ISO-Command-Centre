<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hosting Layout
    |--------------------------------------------------------------------------
    |
    | On the server, the front door (the web folder's index.php) defines
    | ISO_HOME: the private folder holding releases/, shared/, current.php
    | and cron.php. The front door also defines ISO_WEB, the web folder.
    | Both are null in a development checkout.
    |
    */

    'home' => defined('ISO_HOME') ? ISO_HOME : null,

    'web' => defined('ISO_WEB') ? ISO_WEB : null,

    /*
    |--------------------------------------------------------------------------
    | Installed Version
    |--------------------------------------------------------------------------
    |
    | From the VERSION file, always with a leading "v" (release folders and
    | GitHub tags are named like v3.0.0).
    |
    */

    'version' => is_file(base_path('VERSION')) ? 'v'.ltrim(trim((string) file_get_contents(base_path('VERSION'))), 'v') : 'dev',

    /*
    |--------------------------------------------------------------------------
    | Updates
    |--------------------------------------------------------------------------
    |
    | Releases come from this GitHub repository. It is private, so a read-only
    | token (saved on System Settings → Updates) is needed to download them.
    |
    */

    'updates' => [
        'repository' => env('ISO_UPDATE_REPOSITORY', 'KeeSBeeS/ISO-Command-Centre'),
        'api_url' => env('ISO_UPDATE_API_URL', 'https://api.github.com'),
        'token' => env('ISO_GITHUB_TOKEN'),
        'keep_releases' => 3,
        'keep_backups' => 5,
        // Minutes an update may take to finish before it is undone.
        'window' => 15,
    ],

    'display_timezone' => env('ISO_DISPLAY_TIMEZONE', 'Africa/Johannesburg'),

];
