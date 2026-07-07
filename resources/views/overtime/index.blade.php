@extends('layouts.app')
@section('title','Overtime | ISO Admin')
@section('page_title','Overtime')
@section('content_class','content-wide')
@section('content')
<div class="actions" style="justify-content:space-between;margin-bottom:14px">
    <div>
        <h2 style="margin:0">⏱️ Overtime Register</h2>
        <p class="muted" style="margin:5px 0 0">Overtime is linked to the employee, client and site. Entries appear on the calendar and on the employee profile.</p>
    </div>
    @if(auth()->user()->hasPermission('overtime.manage'))
        <a class="btn primary" href="{{ route('overtime.create') }}">➕ Add Overtime</a>
    @endif
</div>

<div class="card">
    <form method="get" class="form-grid overtime-filter">
        <div class="form-row"><label>Month</label><input type="month" name="month" value="{{ $filters['month'] ?? now()->format('Y-m') }}"></div>
        <div class="form-row"><label>Employee</label><select name="employee_id"><option value="">All employees</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected(($filters['employee_id'] ?? '') == $employee->id)>{{ $employee->name }}</option>@endforeach</select></div>
        <div class="form-row"><label>Client</label><select name="client_id"><option value="">All clients</option>@foreach($clients as $client)<option value="{{ $client->id }}" @selected(($filters['client_id'] ?? '') == $client->id)>{{ $client->name }}</option>@endforeach</select></div>
        <div class="form-row" style="align-self:end"><button class="btn primary full" type="submit">🔎 Filter</button></div>
    </form>
</div>
<div style="height:16px"></div>

<div class="grid cols-4 overtime-metrics">
    <div class="card metric"><span>Entries</span><strong>{{ $entries->total() }}</strong></div>
    <div class="card metric"><span>Visible Hours</span><strong>{{ number_format($entries->getCollection()->sum('hours'), 1) }}</strong></div>
    <div class="card metric"><span>Installations</span><strong>{{ $entries->getCollection()->where('is_installation', true)->count() }}</strong></div>
    <div class="card metric"><span>Services</span><strong>{{ $entries->getCollection()->where('is_service', true)->count() }}</strong></div>
</div>
<div style="height:16px"></div>

<div class="overtime-list">
@forelse($entries as $entry)
    <a class="overtime-card card" href="{{ route('overtime.show',$entry) }}">
        <div class="overtime-icon">{{ $entry->type_icon }}</div>
        <div>
            <h3>{{ optional($entry->employee)->name ?? 'Employee removed' }}</h3>
            <p class="muted">{{ optional($entry->overtime_date)->format('Y-m-d') }} · {{ $entry->type_label }}</p>
            <div class="actions"><span class="pill">⏱️ {{ number_format((float)$entry->hours, 2) }} h</span><span class="pill">🏢 {{ optional($entry->client)->name ?? 'No client' }}</span><span class="pill off">📍 {{ optional($entry->site)->name ?? 'No site' }}</span></div>
        </div>
        <div class="overtime-arrow">›</div>
    </a>
@empty
    <div class="card"><p class="muted">No overtime entries found.</p></div>
@endforelse
</div>

<div class="pagination">{{ $entries->links() }}</div>

<style>
.overtime-list{display:grid;gap:12px}.overtime-card{display:grid;grid-template-columns:auto 1fr auto;gap:14px;align-items:center;transition:.2s ease}.overtime-card:hover{transform:translateY(-1px);border-color:rgba(139,220,101,.35)}.overtime-icon{width:52px;height:52px;border-radius:18px;background:rgba(139,220,101,.12);display:grid;place-items:center;font-size:27px}.overtime-card h3{margin:0 0 4px}.overtime-arrow{font-size:34px;color:var(--muted)}@media(max-width:760px){.overtime-card{grid-template-columns:1fr}.overtime-arrow{display:none}.overtime-metrics{grid-template-columns:1fr}.overtime-filter{grid-template-columns:1fr}}
</style>
@endsection
