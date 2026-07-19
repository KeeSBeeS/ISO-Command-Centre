@extends('layouts.app')
@section('title','Update Manager | ISO Admin')
@section('page_title','Update Manager')
@section('content')
<div class="page-head">
    <div class="page-head-main">
        <div class="page-head-icon">⬆️</div>
        <div>
            <h2>Update Manager</h2>
            <p>System Administrator-only platform updates. Upload a deployment ZIP or pull the latest code straight from GitHub, then apply it from the web interface.</p>
        </div>
    </div>
</div>

<div class="alert warning">
    Applying a package overwrites platform files on this server. <strong>.env</strong>, <strong>storage/</strong> and the database are never touched.
    After applying a release that includes a database change, run its <strong>/updates/...</strong> route to finish the update.
</div>

<div class="card" style="margin-bottom:16px">
    <h3>Platform Status</h3>
    <div class="kv-grid">
        <div class="kv"><span>Installed Version</span><strong>{{ $systemVersion ?? 'Unknown' }}</strong></div>
        <div class="kv"><span>Package Files Version</span><strong>{{ $fileVersion ?? 'Unknown' }}</strong></div>
        <div class="kv"><span>PHP ZIP Extension</span><strong>{{ $zipAvailable ? 'Available' : 'Missing' }}</strong></div>
        <div class="kv"><span>File Permissions</span><strong>{{ ($packagesWritable && $baseWritable) ? 'Writable' : 'Not writable' }}</strong></div>
    </div>
    @unless($zipAvailable)
        <div class="alert error" style="margin-top:12px">The PHP ZIP extension is not available on this server. Web updates cannot run without it.</div>
    @endunless
    @unless($baseWritable)
        <div class="alert error" style="margin-top:12px">The platform folder is not writable by the web server, so updates cannot be applied from the web interface.</div>
    @endunless
</div>

<div class="grid cols-2">
    <div class="card">
        <h3>Update via GitHub</h3>
        <p class="muted small">Downloads a ZIP archive of the configured repository branch into the package list below. Nothing is applied until you choose Apply.</p>
        <form method="post" action="{{ route('platform_updates.settings.update') }}">
            @csrf
            @method('PUT')
            <div class="form-row">
                <label>GitHub Repository (owner/repository)</label>
                <input type="text" name="update_github_repository" placeholder="KeeSBeeS/ISO-Command-Centre" value="{{ old('update_github_repository', optional($settings->get('update_github_repository'))->value) }}">
            </div>
            <div class="form-row">
                <label>Branch</label>
                <input type="text" name="update_github_branch" placeholder="main" value="{{ old('update_github_branch', optional($settings->get('update_github_branch'))->value ?? 'main') }}">
            </div>
            <div class="form-row">
                <label>GitHub Access Token {{ $tokenConfigured ? '(configured — leave blank to keep)' : '(optional)' }}</label>
                <input type="password" name="update_github_token" value="" autocomplete="new-password" placeholder="{{ $tokenConfigured ? '••••••••••••' : 'Required for private repositories' }}">
                <p class="muted small">Use a fine-grained token with read-only Contents access to this repository.</p>
            </div>
            @if($tokenConfigured)
                <label class="check" style="margin-bottom:14px">
                    <input type="checkbox" name="update_github_token_clear" value="1">
                    <span>Remove the stored GitHub token</span>
                </label>
            @endif
            <label class="check" style="margin-bottom:14px">
                <input type="checkbox" name="update_backup_before_apply" value="1" @checked(old('update_backup_before_apply', optional($settings->get('update_backup_before_apply'))->value ?? '1') == '1')>
                <span>Create a code backup ZIP before applying any update</span>
            </label>
            <div class="actions">
                <button class="btn primary" type="submit">Save Update Settings</button>
            </div>
        </form>
        <div class="soft-divider"></div>
        <form method="post" action="{{ route('platform_updates.github.download') }}">
            @csrf
            <button class="btn" type="submit" {{ optional($settings->get('update_github_repository'))->value ? '' : 'disabled' }}>Download Latest From GitHub</button>
            @unless(optional($settings->get('update_github_repository'))->value)
                <p class="muted small" style="margin-top:8px">Save a repository first to enable GitHub downloads.</p>
            @endunless
        </form>
    </div>

    <div class="card">
        <h3>Update via ZIP Upload</h3>
        <p class="muted small">Upload a deployment package built the same way as previous ISO Admin releases: platform folders such as <strong>app/</strong>, <strong>resources/</strong> and <strong>routes/</strong> at the ZIP root (or inside one wrapper folder). Partial packages containing only changed files are supported.</p>
        <form method="post" action="{{ route('platform_updates.upload') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-row">
                <label>Update Package (.zip)</label>
                <input type="file" name="package" accept=".zip" required>
            </div>
            <div class="actions">
                <button class="btn primary" type="submit">Upload Package</button>
            </div>
        </form>
        <div class="soft-divider"></div>
        <p class="muted small">
            Never overwritten by an update: <strong>.env</strong>, <strong>storage/</strong> (uploads, backups, logs), <strong>node_modules/</strong> and the local database file.
        </p>
    </div>
</div>

<div style="height:16px"></div>

<div class="card">
    <h3>Update Packages</h3>
    <p class="muted small">Uploaded and downloaded packages waiting to be applied. Applying copies the package files over this installation.</p>
    @if(count($packages))
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Package</th>
                        <th>Size</th>
                        <th>Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($packages as $package)
                        <tr>
                            <td>{{ $package['name'] }}</td>
                            <td>{{ number_format($package['size_bytes'] / 1048576, 2) }} MB</td>
                            <td>{{ $package['modified_at'] }}</td>
                            <td>
                                <div class="actions">
                                    <form method="post" action="{{ route('platform_updates.apply') }}" onsubmit="return confirm('Apply {{ $package['name'] }} now? Platform files will be overwritten.');">
                                        @csrf
                                        <input type="hidden" name="package" value="{{ $package['name'] }}">
                                        <button class="btn primary" type="submit" {{ ($zipAvailable && $baseWritable) ? '' : 'disabled' }}>Apply Update</button>
                                    </form>
                                    <form method="post" action="{{ route('platform_updates.packages.destroy', $package['name']) }}" onsubmit="return confirm('Delete {{ $package['name'] }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn danger" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="muted">No update packages yet. Upload a ZIP or download one from GitHub.</p>
    @endif
</div>

<div style="height:16px"></div>

<div class="card">
    <h3>Code Backups</h3>
    <p class="muted small">Backups created before updates were applied. Backups contain the platform code (excluding vendor/, storage/ and .git) and can be re-applied like any update package after downloading.</p>
    @if(count($backups))
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Backup</th>
                        <th>Size</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($backups as $backup)
                        <tr>
                            <td>{{ $backup['name'] }}</td>
                            <td>{{ number_format($backup['size_bytes'] / 1048576, 2) }} MB</td>
                            <td>{{ $backup['modified_at'] }}</td>
                            <td>
                                <div class="actions">
                                    <a class="btn" href="{{ route('platform_updates.backups.download', $backup['name']) }}">Download</a>
                                    <form method="post" action="{{ route('platform_updates.backups.destroy', $backup['name']) }}" onsubmit="return confirm('Delete {{ $backup['name'] }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn danger" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="muted">No backups yet. A backup is created automatically before each apply while the backup setting is enabled.</p>
    @endif
</div>
@endsection
