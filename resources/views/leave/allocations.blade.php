@extends('layouts.app')
@section('title','Leave Allocations | ISO Admin')
@section('page_title','Leave Allocations')
@section('content')
<style>
    .alloc-inline-form{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .alloc-inline-form input[type=number]{width:90px;padding:9px 10px}
    .num-good{color:#8bdc65;font-weight:850}
    .num-bad{color:#ff9d9f;font-weight:850}
</style>

@if(session('success'))
    <div class="alert success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert error">{{ $errors->first() }}</div>
@endif

<div class="card" style="margin-bottom:16px">
    <div class="page-head-main" style="justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap">
        <div>
            <h2 style="margin:0">Paid Leave Allocations {{ $year }}</h2>
            <p class="muted small" style="margin:6px 0 0">
                The paid leave year runs from 1 January to 31 December. A director sets how many paid leave days each employee receives per year.
                @unless($canManage) You have view-only access; only directors can change allocations. @endunless
            </p>
        </div>
        <form method="get" class="actions">
            <input type="number" name="year" value="{{ $year }}" min="2000" max="2100" style="width:110px">
            <button class="btn" type="submit">Change Year</button>
            <a class="btn" href="{{ route('leave_allocations.index', ['year' => $year - 1]) }}">← {{ $year - 1 }}</a>
            <a class="btn" href="{{ route('leave_allocations.index', ['year' => $year + 1]) }}">{{ $year + 1 }} →</a>
        </form>
    </div>
</div>

<div class="card">
    <h2>Employee Balances</h2>
    <p class="muted small">Used days count approved, deductible leave requests on working days (weekends and public holidays excluded). The sick cycle column shows the current 36-month sick leave cycle.</p>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Allocated {{ $year }}</th>
                    <th>Carried Over</th>
                    <th>Used</th>
                    <th>Remaining</th>
                    <th>Sick Cycle</th>
                    @if($canManage)<th>Set Allocation</th>@endif
                </tr>
            </thead>
            <tbody>
            @forelse($rows as $row)
                @php($employee = $row['employee'])
                @php($summary = $row['summary'])
                @php($cycle = $row['sick_cycle'])
                <tr>
                    <td>
                        <strong>{{ $employee->name }}</strong><br>
                        <span class="muted small">{{ $employee->employee_code ? 'Code: '.$employee->employee_code : $employee->email }}</span>
                    </td>
                    <td>{{ rtrim(rtrim(number_format($summary['allocated_days'], 2), '0'), '.') }}</td>
                    <td>{{ rtrim(rtrim(number_format($summary['carried_over_days'], 2), '0'), '.') }}</td>
                    <td>{{ rtrim(rtrim(number_format($summary['used_normal_days'], 2), '0'), '.') }}</td>
                    <td>
                        <span class="{{ $summary['remaining_normal_days'] < 0 ? 'num-bad' : 'num-good' }}">{{ rtrim(rtrim(number_format($summary['remaining_normal_days'], 2), '0'), '.') }}</span>
                        @if(!$summary['allocation'])
                            <br><span class="muted small">No allocation set</span>
                        @endif
                    </td>
                    <td>
                        {{ $cycle['used_days'] }} / {{ $cycle['entitlement_days'] }} used<br>
                        <span class="muted small">Cycle ends {{ $cycle['cycle_end']->format('Y-m-d') }}</span>
                    </td>
                    @if($canManage)
                    <td>
                        <form method="post" action="{{ route('leave_allocations.store') }}" class="alloc-inline-form">
                            @csrf
                            <input type="hidden" name="user_id" value="{{ $employee->id }}">
                            <input type="hidden" name="year" value="{{ $year }}">
                            <input type="number" name="allocated_days" step="0.5" min="0" max="365" value="{{ $summary['allocated_days'] }}" title="Allocated days">
                            <input type="number" name="carried_over_days" step="0.5" min="0" max="365" value="{{ $summary['carried_over_days'] }}" title="Carried over days">
                            <button class="btn primary" type="submit">Save</button>
                        </form>
                    </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $canManage ? 7 : 6 }}" class="muted">No active employees found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
