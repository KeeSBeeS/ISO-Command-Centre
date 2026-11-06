<?php

namespace App\Support;

use Dotenv\Dotenv;
use RuntimeException;

/**
 * Reads and updates the .env settings file in place.
 *
 * Used by the web installer, since the hosting has no shell to edit it.
 */
class EnvFile
{
    public function __construct(private readonly string $path) {}

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function isWritable(): bool
    {
        return $this->exists() ? is_writable($this->path) : is_writable(dirname($this->path));
    }

    public function get(string $key): ?string
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        return $this->exists() ? Dotenv::parse((string) file_get_contents($this->path)) : [];
    }

    /**
     * Set the given keys, replacing existing lines and appending new ones.
     *
     * @param  array<string, string|int|bool|null>  $values
     */
    public function set(array $values): void
    {
        $contents = $this->exists() ? (string) file_get_contents($this->path) : '';

        foreach ($values as $key => $value) {
            if (! preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                throw new RuntimeException("Invalid setting name [{$key}].");
            }

            $line = $key.'='.self::encode($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            $contents = preg_match($pattern, $contents)
                ? preg_replace_callback($pattern, fn () => $line, $contents, 1)
                : rtrim($contents, "\n").($contents === '' ? '' : "\n").$line."\n";
        }

        // Write to a temporary file first so a failure never leaves a half-written .env.
        $temporary = $this->path.'.tmp';
        if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new RuntimeException("Could not write [{$temporary}].");
        }
        @chmod($temporary, 0600);
        if (! rename($temporary, $this->path)) {
            throw new RuntimeException("Could not replace [{$this->path}].");
        }
    }

    /**
     * Quote a value so it reads back exactly as written. Single quotes are
     * fully literal; values containing one use escaped double quotes.
     */
    public static function encode(string|int|bool|null $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = (string) $value;

        if (str_contains($value, "\n") || str_contains($value, "\r")) {
            throw new RuntimeException('Settings cannot contain line breaks.');
        }

        if (! str_contains($value, "'")) {
            return "'".$value."'";
        }

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }
}
