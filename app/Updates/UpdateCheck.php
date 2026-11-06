<?php

namespace App\Updates;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Looks for a newer release and remembers the answer, so pages can show
 * "update available" without asking GitHub on every visit.
 */
class UpdateCheck
{
    private const CACHE_KEY = 'iso.updates.latest';

    public function __construct(private readonly GitHubReleases $github) {}

    /**
     * @return array{release: ?array<string, ?string>, checked_at: int, error: ?string}
     */
    public function run(): array
    {
        if (! $this->github->hasToken()) {
            $result = ['release' => null, 'checked_at' => time(), 'error' => 'Add a GitHub token to check for updates.'];
        } else {
            try {
                $result = ['release' => $this->github->latest()?->toArray(), 'checked_at' => time(), 'error' => null];
            } catch (Throwable $e) {
                $result = ['release' => $this->last()['release'] ?? null, 'checked_at' => time(), 'error' => $e->getMessage()];
            }
        }
        Cache::forever(self::CACHE_KEY, $result);

        return $result;
    }

    /**
     * @return array{release: ?array<string, ?string>, checked_at: int, error: ?string}|null
     */
    public function last(): ?array
    {
        // Every page asks (for the "update available" notice), so a cache
        // that can't be read must never break a page.
        try {
            return Cache::get(self::CACHE_KEY);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The newest release if it is newer than the running version.
     */
    public function available(): ?Release
    {
        $release = $this->last()['release'] ?? null;

        return $release && Release::isNewer($release['version'], (string) config('iso.version'))
            ? Release::fromArray($release)
            : null;
    }
}
