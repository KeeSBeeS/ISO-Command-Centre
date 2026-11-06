<?php

namespace App\Updates;

/**
 * A release published on GitHub.
 */
final class Release
{
    public function __construct(
        public readonly string $version,
        public readonly string $notes,
        public readonly ?string $publishedAt,
        public readonly string $zipUrl,
        public readonly string $checksumUrl,
    ) {}

    public static function isNewer(string $version, string $than): bool
    {
        return version_compare(ltrim($version, 'v'), ltrim($than, 'v'), '>');
    }

    /**
     * @return array<string, ?string>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * @param  array<string, ?string>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['version'], $data['notes'] ?? '', $data['publishedAt'] ?? null, $data['zipUrl'], $data['checksumUrl']);
    }
}
