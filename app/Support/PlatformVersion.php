<?php

namespace App\Support;

class PlatformVersion
{
    /**
     * The VERSION file in the project root is the single source of truth for the platform version.
     */
    public static function current(): string
    {
        static $version = null;

        if ($version === null) {
            $path = base_path('VERSION');
            $contents = is_readable($path) ? trim((string) file_get_contents($path)) : '';
            $version = $contents !== '' ? $contents : 'unknown';
        }

        return $version;
    }
}
