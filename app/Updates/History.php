<?php

namespace App\Updates;

/**
 * Updates and rollbacks, kept in ISO_HOME/update-history.php rather than
 * the database, because restoring a database backup must not erase it.
 */
class History
{
    public function __construct(private readonly Server $server) {}

    /**
     * @return list<array{type: string, from: string, to: string, backup: ?string, user: ?string, at: int}>
     */
    public function all(): array
    {
        return iso_read_php($this->server->home('update-history.php')) ?? [];
    }

    public function add(string $type, string $from, string $to, ?string $backup, ?string $user): void
    {
        $entries = $this->all();
        array_unshift($entries, compact('type', 'from', 'to', 'backup', 'user') + ['at' => time()]);
        iso_write_php($this->server->home('update-history.php'), array_slice($entries, 0, 50));
    }

    /**
     * The backup taken when ISO Command Centre last moved away from $version: restoring it
     * gives the database exactly as that version left it.
     */
    public function backupLeaving(string $version): ?string
    {
        foreach ($this->all() as $entry) {
            if ($entry['from'] === $version && $entry['backup'] && is_file($entry['backup'])) {
                return $entry['backup'];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function referencedBackups(): array
    {
        return array_values(array_filter(array_column($this->all(), 'backup')));
    }
}
