@extends('layouts.auth')

@section('title', 'Sign in')

@section('content')
    <h2>Welcome back</h2>
    <p class="lead">Sign in to manage your school operations.</p>

    <form method="POST" action="{{ route('login.store') }}">
        @csrf

        <div class="stack" style="gap:14px">
            <x-input name="email" label="Email or username" type="text" placeholder="you@school.test or your username" help="Staff and teacher accounts can sign in with either their email address or username." required autofocus autocomplete="username" />
            <x-input name="password" label="Password" type="password" placeholder="Enter your password" required autocomplete="current-password" />

            <div class="flex-between">
                <label class="checkbox">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    <span>Remember me</span>
                </label>
                <a href="{{ route('password.request') }}" style="font-size:11.5px;color:var(--accent-soft);font-weight:600">Forgot password?</a>
            </div>

            <x-button type="submit" variant="primary" icon="log-out" class="btn-block" style="min-height:44px">Sign in</x-button>
        </div>
    </form>

    @if (app()->environment('local'))
        <div class="info-box" style="margin-top:18px">
            <strong style="color:#e8ddff">Local demo account</strong><br>
            <span class="mono">admin@edusphere.test</span> / <span class="mono">Admin@12345</span>
        </div>
    @endif

    <div class="auth-links">
        <span>Protected by rate limiting and secure password hashing.</span>
    </div>
@endsection
