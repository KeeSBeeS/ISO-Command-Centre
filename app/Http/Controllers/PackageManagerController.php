<?php

namespace App\Http\Controllers;

use App\Models\PackageInstall;
use App\Models\PackageUpload;
use App\Services\PackageManager;
use App\Services\PackageManagerException;
use Illuminate\Http\Request;

class PackageManagerController extends Controller
{
    public function __construct(private PackageManager $packages)
    {
    }

    public function index()
    {
        $this->packages->ensureSchema();

        return view('packages.index', [
            'currentVersion' => $this->packages->currentVersion(),
            'uploads' => PackageUpload::with('uploader')->whereIn('status', ['uploaded', 'invalid'])->latest()->get(),
            'installs' => PackageInstall::with(['installer', 'rolledBackBy'])->orderByDesc('id')->paginate(20),
            'uploadLimit' => $this->packages->uploadLimitBytes(),
            'incomingDirectory' => $this->packages->incomingDirectory(),
            'signingRequired' => $this->packages->signingKey() !== '',
            'rescueEnabled' => $this->packages->rescueEnabled(),
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'package' => ['required', 'file'],
        ], [
            'package.required' => 'Choose a package file. If the file is larger than the server upload limit, use the drop folder instead.',
        ]);

        try {
            $upload = $this->packages->storeUpload($request->file('package'), $request->user());
        } catch (PackageManagerException $e) {
            return back()->withErrors(['package' => $e->getMessage()]);
        }

        if ($upload->status === 'invalid') {
            return redirect()->route('packages.index')->withErrors(['package' => 'Package rejected: ' . $upload->validation_error]);
        }

        return redirect()->route('packages.show', $upload)->with('success', 'Package uploaded. Review the checks below, then install.');
    }

    public function scan(Request $request)
    {
        $count = $this->packages->scanIncoming($request->user());

        return redirect()->route('packages.index')->with('success', $count === 0 ? 'No new packages found in the drop folder.' : "Registered {$count} package(s) from the drop folder.");
    }

    public function show(PackageUpload $upload)
    {
        $this->packages->ensureSchema();

        return view('packages.show', [
            'upload' => $upload,
            'report' => $this->packages->inspect($upload),
        ]);
    }

    public function install(Request $request, PackageUpload $upload)
    {
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Tick the confirmation box to install.']);

        try {
            $install = $this->packages->install($upload, $request->user());
        } catch (PackageManagerException $e) {
            return redirect()->route('packages.show', $upload)->withErrors(['install' => $e->getMessage()]);
        }

        return redirect()->route('packages.history', $install)->with('success', "Version {$install->version} installed. A rollback point was created automatically.");
    }

    public function destroy(PackageUpload $upload)
    {
        $this->packages->deleteUpload($upload);

        return redirect()->route('packages.index')->with('success', 'Package file removed.');
    }

    public function history(PackageInstall $install)
    {
        $this->packages->ensureSchema();
        $install->refresh();

        return view('packages.history', [
            'install' => $install,
            'journal' => $this->packages->journalFor($install),
            'newerInstalls' => PackageInstall::whereIn('status', PackageManager::ROLLBACK_STATUSES)->where('id', '>', $install->id)->orderByDesc('id')->get(),
            'canRollback' => in_array($install->status, PackageManager::ROLLBACK_STATUSES, true),
        ]);
    }

    public function rollback(Request $request, PackageInstall $install)
    {
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Tick the confirmation box to roll back.']);

        try {
            $result = $this->packages->rollback($install, $request->user());
        } catch (PackageManagerException $e) {
            return redirect()->route('packages.history', $install)->withErrors(['rollback' => $e->getMessage()]);
        }

        $message = 'Rolled back: v' . implode(', v', $result['rolled_back']) . '. Platform is now v' . $this->packages->currentVersion() . '.';

        return redirect()->route('packages.history', $install)
            ->with('success', $message)
            ->with('warning', $result['warnings'] === [] ? null : implode(' ', $result['warnings']));
    }

    public function downloadBackup(PackageInstall $install)
    {
        try {
            return response()->download($this->packages->backupArchive($install))->deleteFileAfterSend();
        } catch (PackageManagerException $e) {
            return redirect()->route('packages.history', $install)->withErrors(['backup' => $e->getMessage()]);
        }
    }
}
