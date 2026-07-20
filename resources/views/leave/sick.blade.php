@extends('layouts.app')
@section('title','Sick Leave Register | ISO Admin')
@section('page_title','Sick Leave Register')
@section('content')
<style>
    .cycle-bar{height:8px;border-radius:999px;background:rgba(255,255,255,.08);overflow:hidden;margin-top:6px;min-width:120px}
    .cycle-bar span{display:block;height:100%;background:linear-gradient(90deg,var(--brand),var(--brand3))}
    .cycle-bar.over span{background:linear-gradient(90deg,#e5484d,#ff9d9f)}
    .num-bad{color:#ff9d9f;font-weight:850}
</style>

@if(session('success'))
    <div class="alert success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert error">{{ $errors->first() }}</div>
@endif

<div class="card" style="margin-bottom:16px">
    <h2 style="margin:0">Sick Leave Cycles</h2>
    <p class="muted small" style="margin:6px 0 0">
        Every employee receives {{ $entitlementDays }} working days ({{ (int) round($entitlementDays / 5) }} weeks) of paid sick leave per {{ $cycleMonths }}-month cycle.
        The cycle is anchored to the employee's start date and renews automatically every {{ $cycleMonths }} months.
        Sick days are counted on working days only — weekends and public holidays are excluded.
    </p>
</div>

@if($canManage)
<div class="card" style="margin-bottom:16px">
    <h2>Record Sick Leave</h2>
    <form method="post" action="{{ route('sick_leave.store') }}" class="form-grid" style="grid-template-columns:repeat(4,1fr)">
        @csrf
        <div class="form-row">
            <label>Employee</label>
            <select name="user_id" required>
                <option value="">Select employee…</option>
                @foreach($employees as $employee)
                    <option value="{{ $employee->id }}" @selected((int) old('user_id') === $employee->id)>{{ $employee->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-row"><label>From</label><input type="date" name="sick_from" value="{{ old('sick_from') }}" required></div>
        <div class="form-row"><label>To</label><input type="date" name="sick_to" value="{{ old('sick_to') }}" required></div>
        <div class="form-row" style="align-self:end"><button class="btn primary full" type="submit">Record Sick Leave</button></div>
        <div class="form-row" style="grid-column:1/-1"><label>Notes (optional)</label><input type="text" name="notes" value="{{ old('notes') }}" placeholder="Sick note received, reason, etc." maxlength="500"></div>
    </form>
</div>
@endif

<div class="card" style="margin-bottom:16px">
    <h2>Cycle Balances per Employee</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Employee</th><th>Current Cycle</th><th>Used</th><th>Remaining</th><th>Usage</th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                @php($employee = $row['employee'])
                @php($cycle = $row['cycle'])
                <tr>
                    <td>
                        <strong>{{ $employee->name }}</strong><br>
                        <span class="muted small">{{ $employee->employee_code ? 'Code: '.$employee->employee_code : $employee->email }}</span>
                    </td>
                    <td>
                        {{ $cycle['cycle_start']->format('Y-m-d') }} → {{ $cycle['cycle_end']->format('Y-m-d') }}<br>
                        <span class="muted small">Cycle {{ $cycle['cycle_number'] }} · {{ $cycle['cycle_months'] }} months</span>
                    </td>
                    <td>{{ $cycle['used_days'] }} of {{ $cycle['entitlement_days'] }} day(s)</td>
                    <td>
                        @if($cycle['over_entitlement_days'] > 0)
                            <span class="num-bad">{{ $cycle['over_entitlement_days'] }} day(s) over entitlement</span>
                        @else
                            {{ $cycle['remaining_days'] }} day(s)
                        @endif
                    </td>
                    <td>
                        <div class="cycle-bar {{ $cycle['over_entitlement_days'] > 0 ? 'over' : '' }}"><span style="width:{{ $cycle['used_percent'] }}%"></span></div>
                        <span class="muted small">{{ $cycle['used_percent'] }}%</span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No active employees found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2>Sick Leave Records</h2>
    <p class="muted small">All recorded sick leave, newest first. Removed records are kept for audit but do not count against any cycle.</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Employee</th><th>Dates</th><th>Status</th><th>Recorded By</th><th>Notes</th>@if($canManage)<th></th>@endif</tr></thead>
            <tbody>
            @forelse($records as $record)
                <tr>
                    <td><strong>{{ optional($record->employee)->name ?: 'Unknown' }}</strong></td>
                    <td>{{ $record->date_range_label }}</td>
                    <td>
                        <span class="pill {{ $record->status === 'removed' ? 'off' : '' }}">{{ $record->status_label }}</span>
                        @if($record->status === 'removed' && $record->removal_reason)
                            <br><span class="muted small">{{ $record->removal_reason }}</span>
                        @endif
                    </td>
                    <td>{{ optional($record->marker)->name ?: '-' }}<br><span class="muted small">{{ optional($record->created_at)->format('Y-m-d H:i') }}</span></td>
                    <td>{{ $record->notes ?: '-' }}</td>
                    @if($canManage)
                    <td class="actions right">
                        @if($record->status !== 'removed')
                            <form method="post" action="{{ route('sick_leave.remove', $record) }}" onsubmit="return confirm('Remove this sick leave record? It will no longer count against the cycle.')">
                                @csrf
                                <input type="hidden" name="removal_reason" value="Removed by {{ auth()->user()->name }}">
                                <button class="btn danger" type="submit">Remove</button>
                            </form>
                        @endif
                    </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $canManage ? 6 : 5 }}" class="muted">No sick leave has been recorded yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination">{{ $records->links() }}</div>
</div>
@endsection
