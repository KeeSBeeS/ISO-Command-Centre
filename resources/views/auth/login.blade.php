@extends('layouts.app')
@section('title','Log in | ISO Admin')
@section('content')
<div class="auth-wrap">
    <div class="card auth-card">
        <div class="auth-brand">
            <span class="brand-mark">ISO</span>
            <span><strong>ISO Command Centre</strong><span>Log in to continue</span></span>
        </div>
        <form method="post" action="{{ route('login.attempt') }}">
            @csrf
            <div class="form-row"><label for="login-email">Email</label><input id="login-email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus></div>
            <div class="form-row"><label for="login-password">Password</label><input id="login-password" type="password" name="password" autocomplete="current-password" required></div>
            <label class="check"><input type="checkbox" name="remember" value="1"><span>Keep me signed in</span></label>
            <div style="height:16px"></div>
            <button class="btn primary full" type="submit">Log in</button>
        </form>
    </div>
</div>
@endsection
