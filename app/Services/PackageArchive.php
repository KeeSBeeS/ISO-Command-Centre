<?php

namespace App\Services;

use ZipArchive;

/**
 * Reads and validates an ISO Command Centre update package (.isopkg).
 *
 * A package is a ZIP archive with this layout:
 *
 *   manifest.json            package metadata, file list with SHA-256 hashes
 *   files/<relative path>    files to add or replace, relative to the app root
 *   migrations/<name>.php    optional database migrations (up / down)
 *
 * This class has no Laravel dependencies so tools/build-package.php can use it too.
 */
class PackageArchive
{
    public const FORMAT = 1;
    public const PACKAGE_NAME = 'iso-command-centre';
    public const MANIFEST = 'manifest.json';
    public const FILES_PREFIX = 'files/';
    public const MIGRATIONS_PREFIX = 'migrations/';
    public const EXTENSION = 'isopkg';

    private ZipArchive $zip;
    private array $manifest;

    private function __construct(private string $path)
    {
    }

    public static function open(string $path): self
    {
        if (!class_exists(ZipArchive::class)) {
            throw new PackageManagerException('The PHP zip extension is required to read update packages.');
        }

        if (!is_file($path)) {
            throw new PackageManagerException('Package file not found.');
        }

        $archive = new self($path);
        $archive->zip = new ZipArchive();

        if ($archive->zip->open($path) !== true) {
            throw new PackageManagerException('The uploaded file is not a valid ZIP / .isopkg package.');
        }

        $raw = $archive->zip->getFromName(self::MANIFEST);
        if ($raw === false) {
            throw new PackageManagerException('The package does not contain a manifest.json file at its root.');
        }

        $manifest = json_decode($raw, true);
        if (!is_array($manifest)) {
            throw new PackageManagerException('manifest.json is not valid JSON: ' . json_last_error_msg());
        }

        $archive->manifest = self::validateManifest($manifest);

        return $archive;
    }

    public function __destruct()
    {
        if (isset($this->zip)) {
            @$this->zip->close();
        }
    }

    public function path(): string
    {
        return $this->path;
    }

    public function manifest(): array
    {
        return $this->manifest;
    }

    public function version(): string
    {
        return $this->manifest['version'];
    }

    public function title(): string
    {
        return $this->manifest['title'] ?? ('Update ' . $this->manifest['version']);
    }

    /** @return array<int, array{path: string, sha256: string, size: int}> */
    public function files(): array
    {
        return $this->manifest['files'];
    }

    /** @return array<int, string> */
    public function deletes(): array
    {
        return $this->manifest['delete'];
    }

    /** @return array<int, array{name: string, path: string, sha256: string}> */
    public function migrations(): array
    {
        return $this->manifest['migrations'];
    }

    public function isSigned(): bool
    {
        return !empty($this->manifest['signature']);
    }

    public function signatureValid(string $key): bool
    {
        if (!$this->isSigned() || $key === '') {
            return false;
        }

        return hash_equals(self::signManifest($this->manifest, $key), (string) $this->manifest['signature']);
    }

    /**
     * Confirms every listed file and migration exists in the archive and matches its hash.
     *
     * @return array<int, string> problems found (empty when the package is intact)
     */
    public function verifyContents(): array
    {
        $problems = [];

        foreach ($this->files() as $file) {
            $problems = array_merge($problems, $this->verifyEntry(self::FILES_PREFIX . $file['path'], $file['sha256']));
        }

        foreach ($this->migrations() as $migration) {
            $problems = array_merge($problems, $this->verifyEntry($migration['path'], $migration['sha256']));
        }

        return $problems;
    }

    /**
     * Extracts only the entries named in the manifest into $directory and re-verifies
     * their hashes on disk. Entries not listed in the manifest are never extracted.
     */
    public function extractTo(string $directory): void
    {
        $entries = [];
        foreach ($this->files() as $file) {
            $entries[self::FILES_PREFIX . $file['path']] = $file['sha256'];
        }
        foreach ($this->migrations() as $migration) {
            $entries[$migration['path']] = $migration['sha256'];
        }

        foreach ($entries as $entry => $sha256) {
            $target = rtrim($directory, '/') . '/' . $entry;
            $targetDirectory = dirname($target);

            if (!is_dir($targetDirectory) && !@mkdir($targetDirectory, 0755, true) && !is_dir($targetDirectory)) {
                throw new PackageManagerException("Could not create staging directory {$targetDirectory}.");
            }

            $stream = $this->zip->getStream($entry);
            if ($stream === false) {
                throw new PackageManagerException("Package entry {$entry} could not be read.");
            }

            $out = @fopen($target, 'wb');
            if ($out === false) {
                fclose($stream);
                throw new PackageManagerException("Could not write staging file {$target}.");
            }

            stream_copy_to_stream($stream, $out);
            fclose($stream);
            fclose($out);

            if (!hash_equals($sha256, hash_file('sha256', $target))) {
                throw new PackageManagerException("Checksum mismatch for {$entry} after extraction.");
            }
        }
    }

    public static function validateManifest(array $manifest): array
    {
        if ((int) ($manifest['format'] ?? 0) !== self::FORMAT) {
            throw new PackageManagerException('Unsupported package format. Expected format ' . self::FORMAT . '.');
        }

        if (($manifest['package'] ?? null) !== self::PACKAGE_NAME) {
            throw new PackageManagerException('This package is not for ISO Command Centre (package name "' . ($manifest['package'] ?? 'missing') . '").');
        }

        if (!self::isValidVersion((string) ($manifest['version'] ?? ''))) {
            throw new PackageManagerException('The package version is missing or invalid. Use a version such as 2.9.0.');
        }

        foreach (['min_version', 'max_version'] as $key) {
            $value = $manifest['requires'][$key] ?? null;
            if ($value !== null && $value !== '' && !self::isValidVersion((string) $value)) {
                throw new PackageManagerException("requires.{$key} is not a valid version.");
            }
        }

        $manifest['requires'] = is_array($manifest['requires'] ?? null) ? $manifest['requires'] : [];
        $manifest['files'] = is_array($manifest['files'] ?? null) ? array_values($manifest['files']) : [];
        $manifest['delete'] = is_array($manifest['delete'] ?? null) ? array_values($manifest['delete']) : [];
        $manifest['migrations'] = is_array($manifest['migrations'] ?? null) ? array_values($manifest['migrations']) : [];
        $manifest['changelog'] = is_array($manifest['changelog'] ?? null) ? array_values($manifest['changelog']) : [];

        $seen = [];
        foreach ($manifest['files'] as $index => $file) {
            if (!is_array($file) || !isset($file['path'], $file['sha256'])) {
                throw new PackageManagerException("files[{$index}] must have a path and sha256.");
            }

            $path = self::normalisePath((string) $file['path']);
            if (isset($seen[$path])) {
                throw new PackageManagerException("{$path} is listed more than once.");
            }
            if (!preg_match('/^[a-f0-9]{64}$/', (string) $file['sha256'])) {
                throw new PackageManagerException("{$path} has an invalid sha256 hash.");
            }

            $seen[$path] = true;
            $manifest['files'][$index] = ['path' => $path, 'sha256' => (string) $file['sha256'], 'size' => (int) ($file['size'] ?? 0)];
        }

        foreach ($manifest['delete'] as $index => $path) {
            $path = self::normalisePath((string) $path);
            if (isset($seen[$path])) {
                throw new PackageManagerException("{$path} cannot be both updated and deleted.");
            }
            $seen[$path] = true;
            $manifest['delete'][$index] = $path;
        }

        $migrationNames = [];
        foreach ($manifest['migrations'] as $index => $migration) {
            if (!is_array($migration) || !isset($migration['path'], $migration['sha256'])) {
                throw new PackageManagerException("migrations[{$index}] must have a path and sha256.");
            }

            $path = (string) $migration['path'];
            if (!preg_match('#^migrations/[A-Za-z0-9_.-]+\.php$#', $path)) {
                throw new PackageManagerException("Migration path {$path} must look like migrations/<name>.php.");
            }

            $name = (string) ($migration['name'] ?? basename($path, '.php'));
            if (isset($migrationNames[$name])) {
                throw new PackageManagerException("Migration {$name} is listed more than once.");
            }
            $migrationNames[$name] = true;

            $manifest['migrations'][$index] = ['name' => $name, 'path' => $path, 'sha256' => (string) $migration['sha256']];
        }

        if ($manifest['files'] === [] && $manifest['delete'] === [] && $manifest['migrations'] === []) {
            throw new PackageManagerException('The package does not change any files or run any migrations.');
        }

        return $manifest;
    }

    public static function isValidVersion(string $version): bool
    {
        return (bool) preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version);
    }

    /**
     * Normalises a package-relative path and rejects anything unsafe:
     * absolute paths, traversal, and paths the package manager must never touch.
     */
    public static function normalisePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));

        if ($path === '' || str_contains($path, "\0") || str_starts_with($path, '/') || preg_match('#^[A-Za-z]:#', $path)) {
            throw new PackageManagerException("Invalid package path \"{$path}\".");
        }

        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                throw new PackageManagerException("Package path \"{$path}\" may not contain '..'.");
            }
            $parts[] = $part;
        }

        $normalised = implode('/', $parts);

        if (self::isProtected($normalised)) {
            throw new PackageManagerException("Package path \"{$normalised}\" is protected and cannot be changed by a package.");
        }

        return $normalised;
    }

    public static function isProtected(string $path): bool
    {
        if ($path === '.env' || (str_starts_with($path, '.env.') && $path !== '.env.example')) {
            return true;
        }

        foreach (['.git/', 'storage/', 'bootstrap/cache/', 'node_modules/', 'public/storage/'] as $prefix) {
            if ($path . '/' === $prefix || str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return in_array($path, ['.git', 'public/storage'], true);
    }

    public static function signManifest(array $manifest, string $key): string
    {
        unset($manifest['signature']);

        return hash_hmac('sha256', json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $key);
    }

    private function verifyEntry(string $entry, string $sha256): array
    {
        $stream = $this->zip->getStream($entry);
        if ($stream === false) {
            return ["{$entry} is listed in the manifest but missing from the package."];
        }

        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        return hash_equals($sha256, hash_final($context)) ? [] : ["{$entry} does not match its sha256 hash (corrupt or modified package)."];
    }
}
