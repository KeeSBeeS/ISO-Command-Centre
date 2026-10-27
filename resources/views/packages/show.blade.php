@extends('layouts.app')
@section('title','Review Package | ISO Admin')
@section('page_title','Review Update Package')
@section('content_class','content-wide')
@section('content')
@php
    $manifest = $report['manifest'];
    $actionLabels = ['add' => ['Add', ''], 'replace' => ['Replace', 'warn'], 'unchanged' => ['Unchanged', 'off'], 'delete' => ['Delete', 'warn'], 'already_absent' => ['Already removed', 'off'], 'migration' => ['Migration', '']];
    $planCounts = collect($report['plan'])->countBy('action');
@endphp
<div class="page-head">
    <div class="page-head-main">
        <div class="page-head-icon">📦</div>
        <div>
            <h2>{{ $manifest['title'] ?? $upload->original_name }}</h2>
            <p>v{{ $report['current_version'] }} → v{{ $manifest['version'] ?? '?' }}@if(!empty($manifest['released_at'])) · released {{ $manifest['released_at'] }}@endif</p>
        </div>
    </div>
    <div class="actions"><a class="btn" href="{{ route('packages.index') }}">← Package Manager</a></div>
</div>

@if(!empty($manifest['description']))<div class="card" style="margin-bottom:16px"><p style="margin:0">{{ $manifest['description'] }}</p></div>@endif

<div class="grid cols-2">
    <div class="card">
        <h2>✅ Pre-install Checks</h2>
        <div class="table-wrap">
            <table>
                <tbody>
                @foreach($report['checks'] as $check)
                    <tr>
                        <td style="width:40px">{{ $check['ok'] ? '✅' : '❌' }}</td>
                        <td><strong>{{ $check['label'] }}</strong><br><span class="muted small">{{ $check['message'] }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <h2>🚀 Install</h2>
        <div class="kv-grid" style="margin-bottom:14px">
            <div class="kv"><span>Files added</span><strong>{{ $planCounts['add'] ?? 0 }}</strong></div>
            <div class="kv"><span>Files replaced</span><strong>{{ $planCounts['replace'] ?? 0 }}</strong></div>
            <div class="kv"><span>Files deleted</span><strong>{{ $planCounts['delete'] ?? 0 }}</strong></div>
            <div class="kv"><span>Database migrations</span><strong>{{ $planCounts['migration'] ?? 0 }}</strong></div>
        </div>
        @if($report['can_install'])
            <p class="muted">Every file that is replaced or deleted is backed up first. If anything fails, the install is reverted automatically. You can roll back later from Install History.</p>
            <form method="post" action="{{ route('packages.install', $upload) }}" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').innerText='Installing… do not close this page';">
                @csrf
                <label class="check" style="margin-bottom:12px"><input type="checkbox" name="confirm" value="1" required> <span>I have checked the details and want to install v{{ $manifest['version'] }} now.</span></label>
                <button class="btn primary" type="submit">🚀 Install v{{ $manifest['version'] }}</button>
            </form>
        @else
            <div class="alert error" style="margin:0">This package cannot be installed until every check passes.</div>
        @endif
    </div>
</div>

@if(!empty($manifest['changelog']))
    <div style="height:16px"></div>
    <div class="card">
        <h2>📝 What's in this update</h2>
        <ul>@foreach($manifest['changelog'] as $line)<li>{{ $line }}</li>@endforeach</ul>
    </div>
@endif

<div style="height:16px"></div>
<details class="card">
    <summary style="cursor:pointer"><strong>📄 File plan ({{ count($report['plan']) }} item{{ count($report['plan']) === 1 ? '' : 's' }})</strong></summary>
    <div class="table-wrap" style="margin-top:12px">
        <table>
            <thead><tr><th>Action</th><th>Path</th></tr></thead>
            <tbody>
            @foreach($report['plan'] as $item)
                <tr>
                    <td><span class="pill {{ $actionLabels[$item['action']][1] }}">{{ $actionLabels[$item['action']][0] }}</span></td>
                    <td><code>{{ $item['path'] }}</code></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</details>
@endsection
