@extends('layouts.app')
@section('title','Change Password | ISO Admin')
@section('page_title','Change Password')
@section('content')
<div class="card" style="max-width:620px">
    @if(\Illuminate\Support\Facades\Schema::hasColumn('users','must_change_password') && auth()->user()->must_change_password)
        <div class="alert warning">
            This is your first login with a temporary password. Please change your password before continuing.
        </div>
    @endif
    <form method="post" action="{{ route('password.update') }}">
        @csrf @method('PUT')
        <div class="form-row"><label for="pw-current">Current password</label><input id="pw-current" type="password" name="current_password" autocomplete="current-password" required></div>
        <div class="form-row"><label for="pw-new">New password</label><input id="pw-new" type="password" name="password" autocomplete="new-password" required></div>
        <div class="form-row"><label for="pw-confirm">Confirm new password</label><input id="pw-confirm" type="password" name="password_confirmation" autocomplete="new-password" required></div>
        <button class="btn primary" type="submit">Update password</button>
    </form>
</div>
@endsection
