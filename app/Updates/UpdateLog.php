<?php

namespace App\Updates;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Records what the Update Manager did in the application log
 * (shared/storage/logs). The Updates page's History list, kept outside the
 * database, is the record people read.
 */
class UpdateLog
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function record(string $event, ?User $user, array $context = []): void
    {
        Log::info("platform_update.$event", $context + ['user' => $user?->email]);
    }
}
