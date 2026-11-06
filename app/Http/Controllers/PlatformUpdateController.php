<?php

namespace App\Http\Controllers;

use App\Updates\UpdateLog;
use App\Support\EnvFile;
use App\Updates\GitHubReleases;
use App\Updates\History;
use App\Updates\RecoveryKey;
use App\Updates\Server;
use App\Updates\UpdateCheck;
use App\Updates\Updater;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * The Updates page (System Administrators, or the platform_updates.manage permission): install new releases and switch between
 * installed ones. The work itself is done by App\Updates\Updater.
 */
class PlatformUpdateController extends Controller
{
    /**
     * How often the finish step waits 3 seconds for the new version to take over.
     */
    private const FINISH_ATTEMPTS = 30;

    public function __construct(private readonly Server $server) {}

    public function index(Request $request, UpdateCheck $check, RecoveryKey $recoveryKey, History $history, Updater $updater): Response|RedirectResponse
    {
        if (! $this->server->available()) {
            return response()->view('platform_updates.index', ['available' => false]);
        }

        // An update that hasn't been finished (the browser never reached the
        // finish step) is finished as soon as the new version is running.
        $pending = $updater->pending();
        if ($pending && $pending['to'] === config('iso.version')) {
            return redirect()->route('platform_updates.finish', $pending['token']);
        }

        // The recovery key is created on the first visit and shown only this once.
        $newRecoveryKey = session('recovery_key');
        if (! $recoveryKey->exists()) {
            $newRecoveryKey = $recoveryKey->create();
            UpdateLog::record('recovery_key_created', $request->user());
        }

        $updater->acknowledgeFailure($request->user());

        $current = $this->server->current();
        $installed = collect($this->server->installed())->map(fn (string $version) => [
            'version' => $version,
            'current' => $version === $current,
            'can_switch' => $version !== $current && $this->server->hasUpdateManager($version),
            'has_backup' => $history->backupLeaving($version) !== null,
        ]);

        $response = response()->view('platform_updates.index', [
            'available' => true,
            'current' => $current,
            'last' => $check->last(),
            'update' => $check->available(),
            'hasToken' => app(GitHubReleases::class)->hasToken(),
            'pending' => $updater->pending(),
            'failure' => $updater->lastFailure(),
            'installed' => $installed,
            'history' => $history->all(),
            'newRecoveryKey' => $newRecoveryKey,
            'cronPath' => $this->server->home('cron.php'),
        ]);

        // Still on the old version: look again shortly, until the switch shows.
        return $pending ? $response->header('Refresh', '5') : $response;
    }

    public function check(UpdateCheck $check): RedirectResponse
    {
        $result = $check->run();

        return back()->with($result['error'] ? 'error' : 'status', $result['error'] ?? 'Checked for updates just now.');
    }

    public function saveToken(Request $request, GitHubReleases $github, EnvFile $env): RedirectResponse
    {
        $token = trim((string) $request->validate(['token' => ['required', 'string', 'max:255']])['token']);

        if ($problem = $github->checkToken($token)) {
            return back()->withErrors(['token' => $problem]);
        }

        $env->set(['ISO_GITHUB_TOKEN' => $token]);
        UpdateLog::record('github_token_saved', $request->user());

        return back()->with('status', 'GitHub token saved. Now press "Check for updates".');
    }

    public function install(Request $request, GitHubReleases $github, Updater $updater): RedirectResponse
    {
        $version = $request->validate(['version' => ['required', 'string']])['version'];

        try {
            $release = $github->find($version) ?? throw new RuntimeException("$version was not found on GitHub.");
            $token = $updater->start($release, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        // This next request is handled by the new version.
        return redirect()->route('platform_updates.finish', $token);
    }

    public function finish(Request $request, string $token, Updater $updater): Response|RedirectResponse
    {
        // Right after switching, the server can keep running the old version
        // for a moment (shared hosts cache PHP files). Wait for the new one.
        $pending = $updater->pending();
        if ($pending && hash_equals($pending['token'], $token) && config('iso.version') !== $pending['to']) {
            $attempt = (int) $request->query('attempt');
            if ($attempt < self::FINISH_ATTEMPTS) {
                return response()->view('platform_updates.finishing', ['version' => $pending['to']])
                    ->header('Refresh', '3;url='.route('platform_updates.finish', ['token' => $token, 'attempt' => $attempt + 1]));
            }

            return redirect()->route('platform_updates.index')->with('error', "The server is still running {$pending['from']}. "
                ."Open this page again in a few minutes to finish the update to {$pending['to']}; "
                .'if it never switches, ISO Command Centre undoes the update by itself.');
        }

        try {
            $problem = $updater->finish($token, $request->user());
        } catch (RuntimeException $e) {
            return redirect()->route('platform_updates.index')->with('error', $e->getMessage());
        }

        return redirect()->route('platform_updates.index')->with(...$problem
            ? ['error', 'The update was undone, so nothing changed. Reason: '.$problem]
            : ['status', 'ISO Command Centre is now running '.config('iso.version').'.']);
    }

    public function rollback(Request $request, Updater $updater): RedirectResponse
    {
        $data = $request->validate([
            'version' => ['required', 'string'],
            'mode' => ['required', Rule::in(['database', 'code'])],
        ]);

        try {
            $updater->rollback($data['version'], $data['mode'] === 'database', $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('platform_updates.index')->with('status', "ISO Command Centre is now running {$data['version']}"
            .($data['mode'] === 'database' ? ', with the database as that version left it.' : '.'));
    }

    public function newRecoveryKey(Request $request, RecoveryKey $recoveryKey): RedirectResponse
    {
        $key = $recoveryKey->create();
        UpdateLog::record('recovery_key_created', $request->user());

        return redirect()->route('platform_updates.index')->with('recovery_key', $key);
    }

    public function dismiss(Updater $updater): RedirectResponse
    {
        $updater->dismissFailure();

        return redirect()->route('platform_updates.index');
    }
}
