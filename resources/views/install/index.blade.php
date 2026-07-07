@extends('layouts.app')
@section('title','Install | ISO Admin')
@section('content')
<div style="min-height:calc(100vh - 90px);display:grid;place-items:center">
    <div class="card" style="width:min(620px,100%)">
        <div class="brand" style="padding:0 0 22px">
            <div class="brand-mark">ISO</div>
            <div><strong>ISO Admin Installer</strong><span>Shared-hosting setup, no console required</span></div>
        </div>

        @if($installed)
            <div class="alert success">ISO Admin is already installed.</div>
            <a class="btn primary full" href="{{ route('login') }}">Go to Login</a>
        @else
            <p class="muted">This installer creates the employee, department, role and permission tables. {{ $keyHint }}</p>
            <form method="post" action="{{ route('install.run') }}">
                @csrf
                <div class="form-row"><label>Installer key</label><input type="password" name="installer_key" required></div>
                <div class="form-grid">
                    <div class="form-row"><label>First System Administrator name</label><input type="text" name="name" value="{{ old('name') }}" required></div>
                    <div class="form-row"><label>First System Administrator email</label><input type="email" name="email" value="{{ old('email') }}" required></div>
                </div>
                <div class="form-grid">
                    <div class="form-row"><label>Password</label><input type="password" name="password" required></div>
                    <div class="form-row"><label>Confirm password</label><input type="password" name="password_confirmation" required></div>
                </div>
                <button class="btn primary full" type="submit">Install ISO Admin</button>
            </form>
        @endif
    </div>
</div>
@endsection
