@extends('layouts.app')
@section('title','Install Details | ISO Admin')
@section('page_title','Install Details')
@section('content_class','content-wide')
@section('content')
@php
    $operationLabels = ['create' => ['Added', ''], 'replace' => ['Replaced', 'warn'], 'delete' => ['Deleted', 'warn']];
@endphp
<div class="page-head">
    <div class="page-head-main">
        <div class="page-head-icon">🕘</div>
        <div>
            <h2>v{{ $install->version }} · {{ $install->title }}</h2>
            <p>Installed over v{{ $install->previous_version }} · {{ ucwords(str_replace('_', ' ', $install->status)) }}</p>
        </div>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('packages.index') }}">← Package Manager</a>
        @if(!empty($journal))<a class="btn" href="{{ route('packages.backup', $install) }}">💾 Download Backup</a>@endif
    </div>
</div>

@if($install->error)<div class="alert error">{{ $install->error }}</div>@endif

<div class="grid cols-2">
    <div class="card">
        <h2>📋 Summary</h2>
        <div class="kv-grid">
            <div class="kv"><span>Status</span><strong>{{ ucwords(str_replace('_', ' ', $install->status)) }}</strong></div>
            <div class="kv"><span>Installed</span><strong>{{ optional($install->installed_at ?? $install->created_at)->format('Y-m-d H:i') }} {{ $install->installer->name ?? '' }}</strong></div>
            <div class="kv"><span>Files added / replaced / deleted</span><strong>{{ $install->files_added }} / {{ $install->files_replaced }} / {{ $install->files_deleted }}</strong></div>
            <div class="kv"><span>Migrations</span><strong>{{ $install->migrations_run }}</strong></div>
            @if($install->rolled_back_at)
                <div class="kv"><span>Rolled back</span><strong>{{ $install->rolled_back_at->format('Y-m-d H:i') }} {{ $install->rolledBackBy->name ?? '' }}</strong></div>
            @endif
            <div class="kv"><span>Rollback point</span><strong style="word-break:break-all">{{ $install->install_key }}</strong></div>
        </div>
    </div>
    <div class="card">
        <h2>↩️ Roll Back</h2>
        @if($canRollback)
            @if($install->status === 'rescued')
                <div class="alert warning">Files were already restored by the rescue script. Rolling back now reverts this package's database migrations and the version number.</div>
            @elseif($install->status === 'installing')
                <div class="alert warning">This install was interrupted. Roll it back to restore the previous files.</div>
            @endif
            <p class="muted">Restores every replaced and deleted file, removes added files, reverses this package's database migrations and sets the version back to v{{ $install->previous_version }}.</p>
            @if($newerInstalls->isNotEmpty())
                <div class="alert warning">Newer installs will be rolled back first: v{{ $newerInstalls->pluck('version')->implode(', v') }}.</div>
            @endif
            <form method="post" action="{{ route('packages.rollback', $install) }}" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').innerText='Rolling back… do not close this page';">
                @csrf
                <label class="check" style="margin-bottom:12px"><input type="checkbox" name="confirm" value="1" required> <span>Roll the platform back to v{{ $install->previous_version }}.</span></label>
                <button class="btn danger" type="submit">↩️ Roll Back to v{{ $install->previous_version }}</button>
            </form>
        @else
            <p class="muted">This install is {{ str_replace('_', ' ', $install->status) }} and has no active rollback point.</p>
        @endif
    </div>
</div>

@if(!empty($journal['operations']) || !empty($journal['migrations']))
    <div style="height:16px"></div>
    <details class="card">
        <summary style="cursor:pointer"><strong>📄 Changed files ({{ count($journal['operations']) }}) and migrations ({{ count($journal['migrations']) }})</strong></summary>
        <div class="table-wrap" style="margin-top:12px">
            <table>
                <thead><tr><th>Change</th><th>Path</th><th>State</th></tr></thead>
                <tbody>
                @foreach($journal['operations'] as $operation)
                    <tr>
                        <td><span class="pill {{ $operationLabels[$operation['type']][1] }}">{{ $operationLabels[$operation['type']][0] }}</span></td>
                        <td><code>{{ $operation['path'] }}</code></td>
                        <td class="muted small">{{ !empty($operation['reverted']) ? 'Reverted' : 'Applied' }}</td>
                    </tr>
                @endforeach
                @foreach($journal['migrations'] as $migration)
                    <tr>
                        <td><span class="pill">Migration</span></td>
                        <td><code>{{ $migration['name'] }}</code></td>
                        <td class="muted small">{{ ucwords(str_replace('_', ' ', $migration['status'])) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </details>
@endif

@if($install->log)
    <div style="height:16px"></div>
    <div class="card">
        <h2>🧾 Log</h2>
        <pre style="white-space:pre-wrap;font-size:12px;margin:0">{{ $install->log }}</pre>
    </div>
@endif
@endsection
