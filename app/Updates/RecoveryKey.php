<?php

namespace App\Updates;

use Illuminate\Support\Str;

/**
 * The key for the emergency recovery page (recovery.php). Only a hash is
 * stored; the key itself is shown to a System Administrator once.
 */
class RecoveryKey
{
    public function __construct(private readonly Server $server) {}

    public function exists(): bool
    {
        return is_file($this->server->home('recovery-key.php'));
    }

    /**
     * Create a new key, replacing any old one. Returns it for showing once.
     */
    public function create(): string
    {
        $key = implode('-', array_map(fn () => Str::upper(Str::random(5)), range(1, 5)));
        iso_write_php($this->server->home('recovery-key.php'), ['hash' => password_hash($key, PASSWORD_DEFAULT), 'created' => time()]);

        return $key;
    }
}
