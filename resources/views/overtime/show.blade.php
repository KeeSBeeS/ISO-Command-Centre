@extends('layouts.app')
@section('title','Overtime Entry | ISO Admin')
@section('page_title','Overtime Detail')
@section('content_class','content-wide')
@section('content')
<div class="overtime-detail card">
    <div class="overtime-icon">{{ $entry->type_icon }}</div>
    <div>
        <h2>{{ optional($entry->employee)->name ?? 'Employee removed' }}</h2>
        <p class="muted">{{ optional($entry->overtime_date)->format('Y-m-d') }} · {{ $entry->type_label }}</p>
        <div class="actions"><span class="pill">⏱️ {{ number_format((float)$entry->hours, 2) }} hours</span><span class="pill">🏢 {{ optional($entry->client)->name ?? 'No client' }}</span><span class="pill">📍 {{ optional($entry->site)->name ?? 'No site' }}</span><span class="pill {{ $entry->status === 'approved' ? '' : 'off' }}">{{ ucfirst($entry->status) }}</span></div>
    </div>
    <div class="actions right detail-actions">
        @if($entry->employee)<a class="btn" href="{{ route('employees.show',$entry->employee) }}">👤 Employee</a>@endif
        @if($entry->client)<a class="btn" href="{{ route('clients.show',$entry->client) }}">🏢 Client</a>@endif
        @if(auth()->user()->hasPermission('overtime.manage') && $entry->status !== 'removed')<form method="post" action="{{ route('overtime.destroy',$entry) }}" onsubmit="return confirm('Remove this overtime entry?')">@csrf @method('DELETE')<button class="btn danger" type="submit">🗑️ Remove</button></form>@endif
    </div>
</div>
<div class="detail-grid-wrap" style="margin-top:16px">
    <div class="card"><h2>📍 Site</h2><p><strong>{{ optional($entry->site)->name ?? 'No site' }}</strong></p><p class="muted">{{ optional($entry->site)->location ?? 'No location' }}</p></div>
    <div class="card"><h2>🕒 Time</h2><p><strong>{{ $entry->start_time ?: 'Start not set' }} → {{ $entry->end_time ?: 'End not set' }}</strong></p><p class="muted">Captured by {{ optional($entry->creator)->name ?? 'Unknown' }}</p></div>
    <div class="card"><h2>📝 Note</h2><p>{{ $entry->note ?: 'No note added.' }}</p></div>
</div>
<style>.overtime-detail{display:grid;grid-template-columns:auto 1fr auto;gap:16px;align-items:center}.overtime-icon{width:68px;height:68px;border-radius:22px;background:rgba(139,220,101,.12);display:grid;place-items:center;font-size:34px}.detail-grid-wrap{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}@media(max-width:980px){.overtime-detail,.detail-grid-wrap{grid-template-columns:1fr}.detail-actions{justify-content:flex-start}.detail-actions .btn,.detail-actions form{width:100%}.detail-actions form .btn{width:100%}}</style>
@endsection
