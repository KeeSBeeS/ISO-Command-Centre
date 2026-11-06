<?php

namespace App\Updates;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Installs updates and rolls them back.
 *
 * An update happens in two requests. start() runs in the old release: it
 * downloads, backs up, unpacks, switches and records the update as pending.
 * finish() then runs in the new release, so the database changes come from
 * the new code; if they or the health check fail, it switches back and
 * restores the backup. While an update is pending, the front door switches
 * back if the new release can't start at all, and cron.php undoes it if it
 * was never finished.
 */
class Updater
{
    public function __construct(
        private readonly Server $server,
        private readonly GitHubReleases $github,
        private readonly DatabaseBackup $backups,
        private readonly HealthCheck $health,
        private readonly History $history,
    ) {}

    /**
     * @return array{from: string, to: string, token: string, backup: ?string, migrated: bool, until: int}|null
     */
    public function pending(): ?array
    {
        return iso_read_php($this->server->home('update-pending.php'));
    }

    /**
     * Why the last update was undone, until a System Administrator dismisses it.
     *
     * @return array{version: string, reason: string, database_restored: bool, at: int}|null
     */
    public function lastFailure(): ?array
    {
        return iso_read_php($this->server->home('update-failed.php'));
    }

    public function dismissFailure(): void
    {
        @unlink($this->server->home('update-failed.php'));
    }

    /**
     * Runs in the version ISO Command Centre switched back to: log the failure if the
     * front door or cron.php undid the update (ISO Command Centre wasn't running to log
     * it then), and remove the failed version so nobody switches to it.
     */
    public function acknowledgeFailure(User $user): void
    {
        $failure = $this->lastFailure();
        if (! $failure || ! empty($failure['acknowledged'])) {
            return;
        }

        if (empty($failure['logged'])) {
            UpdateLog::record('update_failed', $user, ['from' => $this->server->current(), 'to' => $failure['version'], 'reason' => $failure['reason']]);
        }
        if ($failure['version'] !== $this->server->current()) {
            Server::deleteDirectory($this->server->release($failure['version']));
        }
        iso_write_php($this->server->home('update-failed.php'), ['acknowledged' => true] + $failure);
    }

    /**
     * Prepare and switch to $release. Returns the token for finish().
     */
    public function start(Release $release, User $user): string
    {
        $current = $this->server->current();
        if ($this->pending()) {
            throw new RuntimeException('Another update is still being finished.');
        }
        if (! Release::isNewer($release->version, $current)) {
            throw new RuntimeException("{$release->version} is not newer than {$current}.");
        }

        @set_time_limit(600);
        ignore_user_abort(true);

        $zip = $this->github->download($release, $this->server->home('updates'));
        try {
            // Left over from an earlier attempt that didn't finish.
            Server::deleteDirectory($this->server->release($release->version));
            iso_setup_unpack($zip, $this->server->home());
        } finally {
            @unlink($zip);
        }

        $backup = $this->backups->create("{$current}-before-{$release->version}");
        $token = Str::random(40);

        iso_write_php($this->server->home('update-pending.php'), [
            'from' => $current,
            'to' => $release->version,
            'token' => $token,
            'backup' => $backup,
            'migrated' => false,
            'until' => time() + 60 * (int) config('iso.updates.window'),
        ]);
        $this->down();

        try {
            $this->server->activate($release->version);
        } catch (Throwable $e) {
            @unlink($this->server->home('update-pending.php'));
            iso_switch($this->server->home(), $current);
            $this->up();
            throw $e;
        }

        UpdateLog::record('update_started', $user, ['from' => $current, 'to' => $release->version]);
        $this->dismissFailure();

        return $token;
    }

    /**
     * Runs in the new release: apply database changes, check health, and
     * keep the update or undo it. Returns null on success, or why it was undone.
     */
    public function finish(string $token, User $user): ?string
    {
        $pending = $this->pending();
        if (! $pending || ! hash_equals($pending['token'], $token)) {
            throw new RuntimeException('There is no update waiting to be finished.');
        }
        if (config('iso.version') !== $pending['to']) {
            throw new RuntimeException("ISO Command Centre is not running {$pending['to']}.");
        }

        @set_time_limit(600);
        ignore_user_abort(true);

        $problems = [];
        try {
            // An upgrade started by the setup file hasn't backed up yet.
            $pending['backup'] ??= $this->backups->create("{$pending['from']}-before-{$pending['to']}");
            $pending['migrated'] = true;
            iso_write_php($this->server->home('update-pending.php'), $pending);

            Artisan::call('migrate', ['--force' => true]);
            $this->recordVersion($pending['to']);
        } catch (Throwable $e) {
            $problems[] = 'Applying the database changes failed: '.$e->getMessage();
        }
        $problems = $problems ?: $this->health->problems();

        if ($problems) {
            $reason = implode(' ', $problems);
            if ($pending['migrated'] && $pending['backup']) {
                $this->backups->restore($pending['backup']);
            }
            iso_revert_pending($this->server->home(), $reason, false, $pending['migrated'] && $pending['backup']);
            UpdateLog::record('update_failed', $user, ['from' => $pending['from'], 'to' => $pending['to'], 'reason' => $reason]);
            iso_write_php($this->server->home('update-failed.php'), ['logged' => true] + $this->lastFailure());

            return $reason;
        }

        @unlink($this->server->home('update-pending.php'));
        $this->up();
        $this->history->add('update', $pending['from'], $pending['to'], $pending['backup'], $user->email);
        $this->server->pruneReleases((int) config('iso.updates.keep_releases'), $pending['from']);
        $this->backups->prune((int) config('iso.updates.keep_backups'), $this->history->referencedBackups());
        $this->dismissFailure();
        UpdateLog::record('update_installed', $user, ['from' => $pending['from'], 'to' => $pending['to']]);

        return null;
    }

    /**
     * Switch to another installed version, optionally restoring the database
     * as that version left it.
     */
    public function rollback(string $version, bool $withDatabase, User $user): void
    {
        $current = $this->server->current();
        if ($this->pending()) {
            throw new RuntimeException('An update is still being finished.');
        }
        if ($version === $current || ! in_array($version, $this->server->installed(), true)) {
            throw new RuntimeException("$version is not an installed version you can switch to.");
        }
        if (! $this->server->hasUpdateManager($version)) {
            throw new RuntimeException("$version has no Update Manager, so you couldn't update again afterwards.");
        }
        $target = $withDatabase ? $this->history->backupLeaving($version) : null;
        if ($withDatabase && ! $target) {
            throw new RuntimeException("There is no database backup from $version. Roll back the code only, or not at all.");
        }

        @set_time_limit(600);
        ignore_user_abort(true);

        // Always keep a way back to exactly where we are now.
        $safety = $this->backups->create("{$current}-before-rollback-to-{$version}");
        $this->down();
        try {
            if ($target) {
                try {
                    $this->backups->restore($target);
                } catch (Throwable $e) {
                    $this->backups->restore($safety);
                    throw $e;
                }
            }
            $this->server->activate($version);
        } finally {
            $this->up();
        }

        $this->history->add('rollback', $current, $version, $safety, $user->email);
        UpdateLog::record('update_rolled_back', $user, ['from' => $current, 'to' => $version, 'database_restored' => (bool) $target]);
    }

    /**
     * Older pages and the /updates/vX-Y-Z steps read the version from
     * system_settings, so keep it in step with the running release.
     */
    private function recordVersion(string $version): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }
        $settings = DB::table('system_settings');
        $settings->where('key', 'platform_version')->exists()
            ? $settings->where('key', 'platform_version')->update(['value' => ltrim($version, 'v'), 'updated_at' => now()])
            : $settings->insert(['key' => 'platform_version', 'group' => 'Core', 'label' => 'Platform Version', 'value' => ltrim($version, 'v'),
                'type' => 'text', 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Maintenance mode. Only the finish step is let through.
     */
    private function down(): void
    {
        app()->maintenanceMode()->activate([
            'except' => [],
            'redirect' => null,
            'retry' => 15,
            'refresh' => 15,
            'secret' => null,
            'status' => 503,
            'template' => null,
        ]);
    }

    private function up(): void
    {
        app()->maintenanceMode()->deactivate();
    }
}
