@extends('layouts.auth')

@section('title', __('Sign in'))

@section('content')
    <h2>{{ __('Welcome back') }}</h2>
    <p class="lead">{{ __('Sign in to manage your school operations.') }}</p>

    <form method="POST" action="{{ route('login.store') }}">
        @csrf

        <div class="stack" style="gap:14px">
            <x-input name="email" :label="__('Email or username')" type="text" :placeholder="__('you@school.test or your username')" :help="__('Staff and teacher accounts can sign in with either their email address or username.')" required autofocus autocomplete="username" />
            <x-input name="password" :label="__('Password')" type="password" :placeholder="__('Enter your password')" required autocomplete="current-password" />

            <div class="flex-between">
                <label class="checkbox">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    <span>{{ __('Remember me') }}</span>
                </label>
                <a href="{{ route('password.request') }}" style="font-size:11.5px;color:var(--accent-soft);font-weight:600">{{ __('Forgot password?') }}</a>
            </div>

            <x-button type="submit" variant="primary" icon="log-out" class="btn-block" style="min-height:44px">{{ __('Sign in') }}</x-button>
        </div>
    </form>

    @if (app()->environment('local'))
        <div class="info-box" style="margin-top:18px">
            <strong style="color:var(--ink)">{{ __('Local demo account') }}</strong><br>
            <span class="mono">admin@edusphere.test</span> / <span class="mono">Admin@12345</span>
        </div>
    @endif

    <div class="auth-links">
        <span>{{ __('Protected by rate limiting and secure password hashing.') }}</span>
    </div>
@endsection
