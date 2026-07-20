<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Services\PlatformUpdateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PlatformUpdateController extends Controller
{
    public function index(Request $request, PlatformUpdateService $updater)
    {
        $this->authorizeSystemAdministrator($request);

        $settings = Schema::hasTable('system_settings')
            ? SystemSetting::where('group', 'Update Manager')->get()->keyBy('key')
            : collect();

        $systemVersion = Schema::hasTable('system_settings')
            ? SystemSetting::valueFor('platform_version', $updater->currentFileVersion())
            : $updater->currentFileVersion();

        return view('settings.updates.index', [
            'settings' => $settings,
            'packages' => $updater->listPackages(),
            'backups' => $updater->listBackups(),
            'systemVersion' => $systemVersion,
            'fileVersion' => $updater->currentFileVersion(),
            'zipAvailable' => $updater->zipExtensionAvailable(),
            'packagesWritable' => is_writable($updater->packagesPath()),
            'baseWritable' => is_writable(base_path()),
            'tokenConfigured' => filled(optional($settings->get('update_github_token'))->value),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $this->authorizeSystemAdministrator($request);
        abort_unless(Schema::hasTable('system_settings'), 404, 'Run the v2.5.3 update first.');

        $data = $request->validate([
            'update_github_repository' => ['nullable', 'string', 'max:255', 'regex:#^[A-Za-z0-9_.\-]+/[A-Za-z0-9_.\-]+$#'],
            'update_github_branch' => ['nullable', 'string', 'max:255'],
            'update_github_token' => ['nullable', 'string', 'max:500'],
            'update_github_token_clear' => ['nullable', 'boolean'],
            'update_backup_before_apply' => ['nullable', 'boolean'],
        ], [
            'update_github_repository.regex' => 'The GitHub repository must be in owner/repository format, for example KeeSBeeS/ISO-Command-Centre.',
        ]);

        $this->storeSetting('update_github_repository', trim((string) ($data['update_github_repository'] ?? '')) ?: null, 'GitHub Repository', 'text', 'GitHub repository in owner/repository format used for web updates.', 10);
        $this->storeSetting('update_github_branch', trim((string) ($data['update_github_branch'] ?? '')) ?: 'main', 'GitHub Branch', 'text', 'Branch downloaded when updating from GitHub.', 20);

        if ($request->boolean('update_github_token_clear')) {
            $this->storeSetting('update_github_token', null, 'GitHub Access Token', 'password', 'Optional token used to download private repositories. Stored server-side only.', 30);
        } elseif (filled($data['update_github_token'] ?? null)) {
            $this->storeSetting('update_github_token', trim((string) $data['update_github_token']), 'GitHub Access Token', 'password', 'Optional token used to download private repositories. Stored server-side only.', 30);
        }

        $this->storeSetting('update_backup_before_apply', $request->boolean('update_backup_before_apply') ? '1' : '0', 'Backup Before Apply', 'boolean', 'Creates a code backup ZIP before applying an update package.', 40);

        return redirect()->route('platform_updates.index')->with('success', 'Update Manager settings saved.');
    }

    public function upload(Request $request, PlatformUpdateService $updater)
    {
        $this->authorizeSystemAdministrator($request);

        $request->validate([
            'package' => ['required', 'file', 'mimes:zip', 'max:262144'],
        ]);

        try {
            $filename = $updater->storeUploadedPackage($request->file('package'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['package' => $exception->getMessage()]);
        }

        return redirect()->route('platform_updates.index')->with('success', 'Update package uploaded: ' . $filename . '. Review it below, then apply when ready.');
    }

    public function downloadGithub(Request $request, PlatformUpdateService $updater)
    {
        $this->authorizeSystemAdministrator($request);
        abort_unless(Schema::hasTable('system_settings'), 404, 'Run the v2.5.3 update first.');

        $repository = (string) SystemSetting::valueFor('update_github_repository', '');
        $branch = (string) SystemSetting::valueFor('update_github_branch', 'main');
        $token = SystemSetting::valueFor('update_github_token');

        if ($repository === '') {
            return back()->withErrors(['github' => 'Save a GitHub repository in the Update Manager settings first.']);
        }

        try {
            $filename = $updater->downloadFromGithub($repository, $branch, $token ?: null);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['github' => $exception->getMessage()]);
        }

        return redirect()->route('platform_updates.index')->with('success', 'Downloaded ' . $repository . ' (' . $branch . ') from GitHub as ' . $filename . '. Apply it below when ready.');
    }

    public function apply(Request $request, PlatformUpdateService $updater)
    {
        $this->authorizeSystemAdministrator($request);

        $data = $request->validate([
            'package' => ['required', 'string', 'regex:/^[A-Za-z0-9._\-]+\.zip$/'],
        ]);

        $backupFirst = !Schema::hasTable('system_settings')
            || SystemSetting::valueFor('update_backup_before_apply', true);

        try {
            $result = $updater->applyPackage($data['package'], (bool) $backupFirst);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['apply' => 'Update failed: ' . $exception->getMessage()]);
        }

        $message = 'Update package applied. Files copied: ' . $result['files_copied'] . '.';
        if ($result['backup_file']) {
            $message .= ' Backup created: ' . $result['backup_file'] . '.';
        }
        if ($result['package_version']) {
            $message .= ' Package version: ' . $result['package_version'] . '.';
        }
        $message .= ' If this release includes a database update, run its /updates/... route now.';

        return redirect()->route('platform_updates.index')->with('success', $message);
    }

    public function destroyPackage(Request $request, PlatformUpdateService $updater, string $filename)
    {
        $this->authorizeSystemAdministrator($request);

        try {
            $updater->deletePackage($filename);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['package' => $exception->getMessage()]);
        }

        return redirect()->route('platform_updates.index')->with('success', 'Update package deleted.');
    }

    public function downloadBackup(Request $request, PlatformUpdateService $updater, string $filename)
    {
        $this->authorizeSystemAdministrator($request);

        try {
            $path = $updater->backupFilePath($filename);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['backup' => $exception->getMessage()]);
        }

        return response()->download($path);
    }

    public function destroyBackup(Request $request, PlatformUpdateService $updater, string $filename)
    {
        $this->authorizeSystemAdministrator($request);

        try {
            $updater->deleteBackup($filename);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['backup' => $exception->getMessage()]);
        }

        return redirect()->route('platform_updates.index')->with('success', 'Backup deleted.');
    }

    private function storeSetting(string $key, ?string $value, string $label, string $type, string $description, int $sortOrder): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => $key],
            [
                'group' => 'Update Manager',
                'label' => $label,
                'value' => $value,
                'type' => $type,
                'description' => $description,
                'sort_order' => $sortOrder,
                'is_core' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function authorizeSystemAdministrator(Request $request): void
    {
        abort_unless($request->user()?->hasRole('system-administrator'), 403, 'Only the System Administrator can manage platform updates.');
    }
}
