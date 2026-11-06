<?php

namespace App\Updates;

use Illuminate\Database\ConnectionInterface;
use PDO;
use RuntimeException;

/**
 * Backs up and restores the MySQL database in plain PHP, so it works on
 * shared hosting where mysqldump may not be available.
 *
 * A backup is a gzipped list of SQL statements, each followed by a line
 * containing only "-- iso:end". iso_restore_backup() in
 * installer/stubs/tools.php.stub reads it back.
 */
class DatabaseBackup
{
    private const ROWS_PER_INSERT = 200;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $directory,
    ) {}

    /**
     * Back up every table. Returns the backup file's path.
     */
    public function create(string $label): string
    {
        is_dir($this->directory) || mkdir($this->directory, 0750, true);
        $label = preg_replace('/[^A-Za-z0-9.\-]+/', '-', $label);
        $path = $this->directory.'/'.gmdate('Ymd-His').'-'.$label.'.sql.gz';

        $pdo = $this->pdo();
        $out = gzopen($path.'.partial', 'wb6');
        if ($out === false) {
            throw new RuntimeException("Could not write the backup file $path.");
        }

        try {
            $write = fn (string $statement) => gzwrite($out, $statement."\n-- iso:end\n");

            foreach ($this->tables($pdo) as $table) {
                $quoted = $this->quoteName($table);
                $create = $pdo->query("SHOW CREATE TABLE $quoted")->fetch(PDO::FETCH_NUM)[1];
                $write("DROP TABLE IF EXISTS $quoted");
                $write($create);

                $rows = $pdo->query("SELECT * FROM $quoted", PDO::FETCH_ASSOC);
                $batch = [];
                $columns = null;
                foreach ($rows as $row) {
                    $columns ??= '('.implode(', ', array_map($this->quoteName(...), array_keys($row))).')';
                    $batch[] = '('.implode(', ', array_map(fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value), $row)).')';
                    if (count($batch) === self::ROWS_PER_INSERT) {
                        $write("INSERT INTO $quoted $columns VALUES ".implode(', ', $batch));
                        $batch = [];
                    }
                }
                if ($batch) {
                    $write("INSERT INTO $quoted $columns VALUES ".implode(', ', $batch));
                }
            }
        } finally {
            gzclose($out);
        }

        // Only a complete backup gets its real name.
        rename($path.'.partial', $path);
        @chmod($path, 0600);

        return $path;
    }

    public function restore(string $path): void
    {
        Server::loadTools();
        iso_restore_backup($this->pdo(), $path);
    }

    /**
     * Keep the newest $keep backups, plus any in $protect.
     *
     * @param  list<string>  $protect
     */
    public function prune(int $keep, array $protect = []): void
    {
        $backups = glob($this->directory.'/*.sql.gz') ?: [];
        rsort($backups);
        foreach (array_slice($backups, $keep) as $backup) {
            if (! in_array($backup, $protect, true)) {
                @unlink($backup);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function tables(PDO $pdo): array
    {
        return $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    private function quoteName(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }

    private function pdo(): PDO
    {
        if ($this->connection->getDriverName() !== 'mysql' && $this->connection->getDriverName() !== 'mariadb') {
            throw new RuntimeException('Database backups need MySQL.');
        }

        return $this->connection->getPdo();
    }
}
