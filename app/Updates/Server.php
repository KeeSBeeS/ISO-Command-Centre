<?php

namespace App\Updates;

use RuntimeException;

/**
 * The private folder (ISO_HOME) and web folder on the server, and the
 * plain-PHP server tools shared with the front door, cron.php and recovery.php.
 */
class Server
{
    public function __construct(
        private readonly ?string $home,
        private readonly ?string $web,
    ) {
        self::loadTools();
    }

    /**
     * installer/stubs/tools.php.stub and the setup file's library functions,
     * so switching, unpacking and restoring have a single implementation.
     */
    public static function loadTools(): void
    {
        require_once base_path('installer/stubs/tools.php.stub');
        if (! defined('ISO_SETUP_LIBRARY')) {
            define('ISO_SETUP_LIBRARY', true);
        }
        require_once base_path('installer/iso-setup.php');
    }

    /**
     * Updates only work on the server, where the front door defines both folders.
     */
    public function available(): bool
    {
        return $this->home !== null && $this->web !== null;
    }

    public function home(string $path = ''): string
    {
        if ($this->home === null) {
            throw new RuntimeException('Updates only work on the server, where ISO_HOME is defined.');
        }

        return rtrim($this->home.'/'.$path, '/');
    }

    public function web(): string
    {
        if ($this->web === null) {
            throw new RuntimeException('Updates only work on the server, where ISO_WEB is defined.');
        }

        return $this->web;
    }

    public function release(string $version): string
    {
        return $this->home('releases/'.$version);
    }

    public function current(): string
    {
        return (string) iso_read_php($this->home('current.php'));
    }

    /**
     * @return list<string> newest first
     */
    public function installed(): array
    {
        return iso_installed_releases($this->home());
    }

    /**
     * Whether a release contains the Update Manager, so it can update again
     * after being rolled back to.
     */
    public function hasUpdateManager(string $version): bool
    {
        return is_file($this->release($version).'/app/Updates/Updater.php');
    }

    /**
     * Make $version the active release. The server files are always written
     * from the newest installed release's stubs, which work with any release.
     */
    public function activate(string $version): void
    {
        $newest = collect($this->installed())
            ->first(fn ($v) => is_file($this->release($v).'/installer/stubs/tools.php.stub'));

        iso_setup_publish($this->release($newest ?? $version), $this->home(), $this->web());
        // Assets of the release being activated (no-op when it is the newest).
        iso_setup_copy_public($this->release($version).'/public', $this->web());
        iso_switch($this->home(), $version);
    }

    /**
     * Remove the oldest releases, never the current one or $keepAlso.
     */
    public function pruneReleases(int $keep, ?string $keepAlso = null): void
    {
        $current = $this->current();
        foreach (array_slice($this->installed(), $keep) as $version) {
            if ($version !== $current && $version !== $keepAlso) {
                self::deleteDirectory($this->release($version));
            }
        }
    }

    public static function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}
