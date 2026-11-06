@extends('layouts.app')
@section('title','Install | ISO Admin')
@section('content')
<div class="auth-wrap">
    <div class="card auth-card wide">
        <div class="auth-brand">
            <span class="brand-mark">ISO</span>
            <span><strong>ISO Command Centre installer</strong><span>Creates the database tables and your System Administrator account</span></span>
        </div>

        @if($installed)
            <div class="alert success">ISO Admin is already installed.</div>
            <a class="btn primary full" href="{{ route('login') }}">Go to Login</a>
        @else
            <p class="muted">This installer creates the employee, department, role and permission tables. {{ $keyHint }}</p>
            <form method="post" action="{{ route('install.run') }}">
                @csrf
                <div class="form-row"><label for="inst-installer_key">Installer key</label><input id="inst-installer_key" type="password" name="installer_key" required></div>
                <div class="form-grid">
                    <div class="form-row"><label for="inst-name">First System Administrator name</label><input id="inst-name" type="text" name="name" value="{{ old('name') }}" required></div>
                    <div class="form-row"><label for="inst-email">First System Administrator email</label><input id="inst-email" type="email" name="email" value="{{ old('email') }}" required></div>
                </div>
                <div class="form-grid">
                    <div class="form-row"><label for="inst-password">Password</label><input id="inst-password" type="password" name="password" required></div>
                    <div class="form-row"><label for="inst-password_confirmation">Confirm password</label><input id="inst-password_confirmation" type="password" name="password_confirmation" required></div>
                </div>
                <button class="btn primary full" type="submit">Install ISO Admin</button>
            </form>
        @endif
    </div>
</div>
@endsection
