@extends('layouts.app')
@section('title','Login | ISO Admin')
@section('content')
<div style="min-height:calc(100vh - 90px);display:grid;place-items:center">
    <div class="card" style="width:min(440px,100%)">
        <div class="brand" style="padding:0 0 22px">
            <div class="brand-mark">ISO</div>
            <div><strong>ISO Admin</strong><span>Central Command Login</span></div>
        </div>
        <form method="post" action="{{ route('login.attempt') }}">
            @csrf
            <div class="form-row"><label>Email</label><input type="email" name="email" value="{{ old('email') }}" required autofocus></div>
            <div class="form-row"><label>Password</label><input type="password" name="password" required></div>
            <label class="check"><input type="checkbox" name="remember" value="1"><span>Keep me signed in</span></label>
            <div style="height:14px"></div>
            <button class="btn primary full" type="submit">Login</button>
        </form>
    </div>
</div>
@endsection
