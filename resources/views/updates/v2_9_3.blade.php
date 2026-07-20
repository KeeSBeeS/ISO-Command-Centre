@extends('layouts.app')

@section('title','Update v2.9.3')
@section('page_title','Update v2.9.3')
@section('page_icon','🛠️')

@section('content')
<div class="card">
    <h2>ISO Admin Command Framework v2.9.3</h2>
    <p class="muted">This feature update adds a date-range Time &amp; Attendance register to the employee profile page, modelled on the biometric Start/End Work Time and Late reports.</p>

    <div class="grid two" style="margin-top:16px">
        <div class="mini-card">
            <strong>Current Version</strong>
            <span>{{ $systemVersion ? 'v'.$systemVersion : 'Unknown' }}</span>
        </div>
        <div class="mini-card">
            <strong>Adds</strong>
            <span>Employee Time &amp; Attendance date-range register</span>
        </div>
    </div>

    <div class="alert warning" style="margin-top:16px">
        This update has no database schema changes. Applying it clears the compiled views and cache (so the new employee page shows immediately), updates the platform version and re-syncs System Administrator permissions.
    </div>

    <p class="muted small" style="margin-top:14px">
        Before applying, make sure the two changed code files are already uploaded:
        <br><code>app/Http/Controllers/EmployeeController.php</code>
        <br><code>resources/views/employees/show.blade.php</code>
    </p>

    <form method="post" action="{{ route('updates.v2_9_3.apply') }}" style="margin-top:18px">
        @csrf
        <button class="btn primary" type="submit">Apply v2.9.3</button>
        <a class="btn" href="{{ route('employees.index') }}">Back to Employees</a>
    </form>
</div>
@endsection
