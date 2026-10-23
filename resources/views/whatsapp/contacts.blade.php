@extends('layouts.app')
@section('title','WhatsApp Employee Opt-ins | ISO Admin')
@section('page_title','WhatsApp Employee Opt-ins')
@section('content')
@include('whatsapp.partials.tabs')
<div class="page-head">
    <div class="page-head-main">
        <div class="page-head-icon">👥</div>
        <div>
            <h2>Employee WhatsApp Directory</h2>
            <p>{{ $optedInCount }} employee(s) opted in. Numbers come from the employee profile mobile, then phone fields.</p>
        </div>
    </div>
    <form method="get" action="{{ route('whatsapp.contacts') }}" class="actions">
        <input type="search" name="q" value="{{ $search }}" placeholder="Search employees">
    </form>
</div>

<div class="alert warning">Only record an opt-in when the employee has agreed to receive WhatsApp messages from ISO (for example in a signed policy or verbally confirmed). Employees can also reply START or STOP on WhatsApp.</div>

<form method="post" action="{{ route('whatsapp.contacts.consent') }}">
    @csrf
    <div class="table-wrap">
        <table>
            <thead><tr><th style="width:40px"><input type="checkbox" onclick="document.querySelectorAll('.wa-user').forEach(c => c.checked = this.checked)" aria-label="Select all"></th><th>Employee</th><th>WhatsApp number</th><th>Consent</th><th>Last message</th><th></th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                @php($contact = $row['contact'])
                <tr>
                    <td><input class="wa-user" type="checkbox" name="user_ids[]" value="{{ $row['user']->id }}" @disabled(!$row['number']) style="width:auto"></td>
                    <td><strong>{{ $row['user']->name }}</strong><div class="muted small">{{ $row['user']->position ?: optional($row['user']->profile)->job_title }}</div></td>
                    <td>{!! $row['number'] ? e('+' . $row['number']) : '<span class="pill warn">No valid number</span>' !!}</td>
                    <td>
                        @if($contact?->isOptedOut())
                            <span class="pill warn">Opted out {{ $contact->opted_out_at->format('d M Y') }}</span>
                        @elseif($contact?->isOptedIn())
                            <span class="pill" title="{{ $contact->opt_in_source }}">Opted in {{ $contact->opted_in_at->format('d M Y') }}</span>
                        @else
                            <span class="pill off">Not recorded</span>
                        @endif
                    </td>
                    <td class="small muted">{{ $contact?->last_message_at?->diffForHumans() ?? '—' }}</td>
                    <td>@if($contact && $contact->last_message_at)<a class="btn" href="{{ route('whatsapp.inbox.show', $contact) }}">Open chat</a>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No active employees found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination">{{ $users->links() }}</div>
    <div class="actions right" style="margin-top:12px">
        <button class="btn" type="submit" name="action" value="opt_out">Record opt-out for selected</button>
        <button class="btn primary" type="submit" name="action" value="opt_in">Record opt-in for selected</button>
    </div>
</form>
@endsection
