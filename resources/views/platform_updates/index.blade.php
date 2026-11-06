@extends('layouts.app')
@section('title', 'Updates | ISO Admin')
@section('page_title', 'Updates')
@section('content')
@php
    $tz = config('iso.display_timezone');
    $at = fn (int $timestamp) => \Illuminate\Support\Carbon::createFromTimestamp($timestamp)->timezone($tz)->format('Y-m-d H:i');
@endphp

@if(session('status'))<div class="alert success">{{ session('status') }}</div>@endif
@if(session('error'))<div class="alert error">{{ session('error') }}</div>@endif

@if(! $available)
    <div class="card">
        <h2>Updates</h2>
        <p class="muted">Updates only work on the server. This copy of ISO Command Centre is a development checkout.</p>
    </div>
@else
    <div class="grid" style="gap:14px">
        @if($newRecoveryKey)
            <div class="card" style="border-color:rgba(245,185,76,.55)">
                <h2>Your recovery key: save it now</h2>
                <p class="muted">
                    If an update ever breaks ISO Command Centre so badly that it won't open, go to
                    <strong>{{ url('/recovery.php') }}</strong> and enter this key to switch back to a version that worked.
                    Keep it somewhere safe, such as your password manager. <strong>It won't be shown again.</strong>
                </p>
                <input type="text" readonly value="{{ $newRecoveryKey }}" onclick="this.select()" style="font-family:monospace;font-size:18px;text-align:center;letter-spacing:.08em">
            </div>
        @endif

        @if($failure)
            <div class="card" style="border-color:rgba(229,72,77,.55)">
                <h2>The update to {{ $failure['version'] }} was undone</h2>
                <p style="word-break:break-word">{{ $failure['reason'] }}</p>
                <p class="muted small">
                    ISO Command Centre switched back to {{ $current }}{{ $failure['database_restored'] ? ' and restored the database backup' : '' }}.
                    Nothing was lost. Send this message to whoever builds ISO Command Centre.
                </p>
                <form method="post" action="{{ route('platform_updates.dismiss') }}">@csrf<button class="btn" type="submit">Dismiss</button></form>
            </div>
        @endif

        @if($pending)
            <div class="alert warning">
                An update from {{ $pending['from'] }} to {{ $pending['to'] }} hasn't finished yet. It finishes by itself as soon as the server
                is running {{ $pending['to'] }}; this page checks every few seconds. If that never happens, the update is undone at {{ $at($pending['until']) }}.
            </div>
        @endif

        <div class="card">
            <h2>Version</h2>
            <p>ISO Command Centre is running <span class="pill">{{ $current }}</span></p>

            @if($update)
                <div class="card" style="box-shadow:none;margin:12px 0;border-color:rgba(139,220,101,.35)">
                    <h3 style="margin-top:0">{{ $update->version }} is available</h3>
                    @if($update->notes)
                        <div class="small muted" style="max-height:240px;overflow:auto;white-space:pre-line;margin-bottom:12px">{{ $update->notes }}</div>
                    @endif
                    <form method="post" action="{{ route('platform_updates.install') }}"
                          onsubmit="return confirm('Install {{ $update->version }}? The database is backed up first, and the site switches back by itself if anything goes wrong.')">
                        @csrf
                        <input type="hidden" name="version" value="{{ $update->version }}">
                        <button class="btn primary" type="submit">Install {{ $update->version }}</button>
                    </form>
                    <p class="muted small">Takes up to a minute. The site is unavailable for a few seconds while it switches.</p>
                </div>
            @elseif($hasToken)
                <p class="muted">You're up to date.</p>
            @endif

            <div class="actions">
                @if($last)
                    <span class="muted small">Last checked {{ $at($last['checked_at']) }}.</span>
                @endif
                @if($last['error'] ?? null)
                    <span class="small" style="color:var(--warn)">{{ $last['error'] }}</span>
                @endif
                <form method="post" action="{{ route('platform_updates.check') }}">@csrf<button class="btn" type="submit">Check for updates</button></form>
            </div>
        </div>

        <div class="card">
            <h2>GitHub connection</h2>
            @if($hasToken)
                <p class="muted">Connected. ISO Command Centre checks for new versions every hour.</p>
            @else
                <p>ISO Command Centre needs a read-only GitHub token to download new versions from your private repository.</p>
            @endif
            <details @if(! $hasToken || $errors->has('token')) open @endif>
                <summary style="cursor:pointer;margin-bottom:10px">{{ $hasToken ? 'Replace the token' : 'How to create the token' }}</summary>
                <ol class="muted small">
                    <li>On GitHub, open <strong>Settings → Developer settings → Personal access tokens → Fine-grained tokens</strong>.</li>
                    <li>Click <strong>Generate new token</strong>. Name it "ISO Command Centre updates" and pick an expiry date (up to a year).</li>
                    <li>Under <strong>Repository access</strong>, choose <strong>Only select repositories</strong> and pick {{ config('iso.updates.repository') }}.</li>
                    <li>Under <strong>Permissions → Repository permissions</strong>, set <strong>Contents</strong> to <strong>Read-only</strong>.</li>
                    <li>Click <strong>Generate token</strong>, copy it and paste it below.</li>
                </ol>
                <form method="post" action="{{ route('platform_updates.token') }}">
                    @csrf
                    <label for="token">GitHub token</label>
                    <input id="token" name="token" type="password" autocomplete="off" required>
                    <div style="height:10px"></div>
                    <button class="btn primary" type="submit">Save token</button>
                </form>
            </details>
        </div>

        <div class="card">
            <h2>Installed versions</h2>
            <p class="muted small">
                Switch back if a new version causes problems. "With database" also puts the database back exactly as that version
                left it: anything changed in ISO Command Centre since then is lost.
            </p>
            <div class="table-wrap">
                <table style="min-width:0">
                    <tbody>
                    @foreach($installed as $release)
                        <tr>
                            <td><strong>{{ $release['version'] }}</strong></td>
                            <td>
                                @if($release['current'])
                                    <span class="pill">running</span>
                                @elseif(! $release['can_switch'])
                                    <span class="muted small">Can't switch back from the Updates page (this version has no Update Manager). Use the recovery page if you need it.</span>
                                @else
                                    <div class="actions right">
                                        @if($release['has_backup'])
                                            <form method="post" action="{{ route('platform_updates.rollback') }}"
                                                  onsubmit="return confirm('Switch to {{ $release['version'] }} and restore the database as it was then? Changes made since then are lost.')">
                                                @csrf
                                                <input type="hidden" name="version" value="{{ $release['version'] }}">
                                                <input type="hidden" name="mode" value="database">
                                                <button class="btn danger" type="submit">Switch, with database</button>
                                            </form>
                                        @endif
                                        <form method="post" action="{{ route('platform_updates.rollback') }}"
                                              onsubmit="return confirm('Switch to {{ $release['version'] }}, keeping the database as it is?')">
                                            @csrf
                                            <input type="hidden" name="version" value="{{ $release['version'] }}">
                                            <input type="hidden" name="mode" value="code">
                                            <button class="btn" type="submit">Switch, code only</button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if($history)
            <div class="card">
                <h2>History</h2>
                <ul class="small" style="margin:0;padding-left:18px">
                    @foreach($history as $entry)
                        <li class="muted">
                            {{ $at($entry['at']) }} ·
                            {{ $entry['type'] === 'update' ? 'Updated' : 'Switched' }} {{ $entry['from'] }} → {{ $entry['to'] }}
                            @if($entry['user']) by {{ $entry['user'] }} @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid cols-2">
            <div class="card">
                <h2>Background tasks</h2>
                <p class="muted small">Add this cron job in DirectAdmin (<strong>Advanced Features → Cron Jobs</strong>), set to run every minute.
                    It checks for updates, and undoes an update that was never finished.</p>
                <input type="text" readonly value="/usr/local/bin/php {{ $cronPath }} >/dev/null 2>&1" onclick="this.select()" style="font-family:monospace">
            </div>
            <div class="card">
                <h2>Emergency recovery</h2>
                <p class="muted small">
                    If ISO Command Centre ever won't open, use <strong>{{ url('/recovery.php') }}</strong> with your recovery key.
                    Lost the key? Create a new one; the old one stops working.
                </p>
                <form method="post" action="{{ route('platform_updates.recovery_key') }}"
                      onsubmit="return confirm('Create a new recovery key? The old one stops working.')">
                    @csrf
                    <button class="btn" type="submit">Create a new recovery key</button>
                </form>
            </div>
        </div>
    </div>
@endif
@endsection
