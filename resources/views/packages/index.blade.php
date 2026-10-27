@extends('layouts.app')
@section('title','Package Manager | ISO Admin')
@section('page_title','Package Manager')
@section('content_class','content-wide')
@section('content')
@php
    $formatBytes = fn ($bytes) => $bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : number_format($bytes / 1024, 1) . ' KB';
    $statusPill = fn ($status) => match ($status) { 'installed', 'uploaded' => '', 'rolled_back' => 'off', default => 'warn' };
@endphp
<div class="page-head">
    <div class="page-head-main">
        <div class="page-head-icon">📦</div>
        <div>
            <h2>Package Manager</h2>
            <p>Upload update packages, install them with an automatic rollback point, and roll back any install.</p>
        </div>
    </div>
</div>

<div class="grid cols-4" style="margin-bottom:16px">
    <div class="card metric"><span>Installed Version</span><strong>v{{ $currentVersion }}</strong></div>
    <div class="card metric"><span>Waiting to Install</span><strong>{{ $uploads->where('status', 'uploaded')->count() }}</strong></div>
    <div class="card metric"><span>Signed Packages</span><strong>{{ $signingRequired ? 'Required' : 'Optional' }}</strong></div>
    <div class="card metric"><span>Rescue Script</span><strong>{{ $rescueEnabled ? 'Enabled' : 'Disabled' }}</strong></div>
</div>

<div class="grid cols-2">
    <div class="card">
        <h2>⬆️ Upload an Update Package</h2>
        <p class="muted">Upload the <strong>.isopkg</strong> file you were given. Nothing changes until you review the checks and press Install.</p>
        <form method="post" action="{{ route('packages.upload') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-row">
                <label>Package file (.isopkg)</label>
                <input type="file" name="package" accept=".isopkg,.zip" required>
                <p class="muted small" style="margin:7px 0 0">Server upload limit: {{ $uploadLimit ? $formatBytes($uploadLimit) : 'unknown' }}.</p>
            </div>
            <div class="actions"><button class="btn primary" type="submit">⬆️ Upload Package</button></div>
        </form>
    </div>
    <div class="card">
        <h2>📂 Drop Folder (large packages)</h2>
        <p class="muted">If a package is bigger than the upload limit, copy it with FTP or the hosting File Manager into this folder, then press Scan:</p>
        <div class="kv" style="margin-bottom:12px"><span>Drop folder</span><strong style="word-break:break-all">{{ $incomingDirectory }}</strong></div>
        <form method="post" action="{{ route('packages.scan') }}">
            @csrf
            <button class="btn" type="submit">🔍 Scan Drop Folder</button>
        </form>
    </div>
</div>

<div style="height:16px"></div>
<div class="card">
    <h2>🗂️ Uploaded Packages</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Package</th><th>Version</th><th>Size</th><th>Uploaded</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($uploads as $upload)
                <tr>
                    <td><strong>{{ $upload->title ?? $upload->original_name }}</strong><br><span class="muted small">{{ $upload->original_name }}</span></td>
                    <td>{{ $upload->version ? 'v' . $upload->version : '—' }}</td>
                    <td>{{ $formatBytes($upload->size) }}</td>
                    <td>{{ optional($upload->created_at)->format('Y-m-d H:i') }}<br><span class="muted small">{{ $upload->uploader->name ?? ($upload->source === 'drop_folder' ? 'Drop folder' : '') }}</span></td>
                    <td>
                        <span class="pill {{ $statusPill($upload->status) }}">{{ ucfirst($upload->status) }}</span>
                        @if($upload->validation_error)<br><span class="muted small">{{ $upload->validation_error }}</span>@endif
                    </td>
                    <td>
                        <div class="actions">
                            @if($upload->status === 'uploaded')<a class="btn primary" href="{{ route('packages.show', $upload) }}">Review &amp; Install</a>@endif
                            <form method="post" action="{{ route('packages.destroy', $upload) }}" onsubmit="return confirm('Remove this package file?')">
                                @csrf @method('DELETE')
                                <button class="btn danger" type="submit">Remove</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No packages waiting. Upload one above.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div style="height:16px"></div>
<div class="card">
    <h2>🕘 Install History &amp; Rollback Points</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Version</th><th>From</th><th>Changes</th><th>Installed</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($installs as $install)
                <tr>
                    <td><strong>v{{ $install->version }}</strong><br><span class="muted small">{{ $install->title }}</span></td>
                    <td>v{{ $install->previous_version }}</td>
                    <td class="small">+{{ $install->files_added }} · ~{{ $install->files_replaced }} · −{{ $install->files_deleted }}@if($install->migrations_run) · {{ $install->migrations_run }} migration(s)@endif</td>
                    <td>{{ optional($install->installed_at ?? $install->created_at)->format('Y-m-d H:i') }}<br><span class="muted small">{{ $install->installer->name ?? '' }}</span></td>
                    <td>
                        <span class="pill {{ $statusPill($install->status) }}">{{ ucwords(str_replace('_', ' ', $install->status)) }}</span>
                        @if($install->rolled_back_at)<br><span class="muted small">{{ $install->rolled_back_at->format('Y-m-d H:i') }} {{ $install->rolledBackBy->name ?? '' }}</span>@endif
                    </td>
                    <td><a class="btn" href="{{ route('packages.history', $install) }}">Details{{ in_array($install->status, \App\Services\PackageManager::ROLLBACK_STATUSES, true) ? ' / Rollback' : '' }}</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No packages installed through the Package Manager yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination">{{ $installs->links() }}</div>
</div>
@endsection
