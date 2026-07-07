@extends('layouts.app')
@section('title','Change Password | ISO Admin')
@section('page_title','Change Password')
@section('content')
<div class="card" style="max-width:620px">
    @if(\Illuminate\Support\Facades\Schema::hasColumn('users','must_change_password') && auth()->user()->must_change_password)
        <div class="alert" style="background:rgba(245,185,76,.12);border-color:rgba(245,185,76,.35)">
            This is your first login with a temporary password. Please change your password before continuing.
        </div>
    @endif
    <form method="post" action="{{ route('password.update') }}">
        @csrf @method('PUT')
        <div class="form-row"><label>Current password</label><input type="password" name="current_password" required></div>
        <div class="form-row"><label>New password</label><input type="password" name="password" required></div>
        <div class="form-row"><label>Confirm new password</label><input type="password" name="password_confirmation" required></div>
        <button class="btn primary" type="submit">Update Password</button>
    </form>
</div>
@endsection
