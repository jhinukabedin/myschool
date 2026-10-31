@extends('layouts.auth')

@section('title', 'Forgot password')

@section('content')
    <h2>Reset your password</h2>
    <p class="lead">Enter your account email and we will send a secure reset link.</p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="stack" style="gap:14px">
            <x-input name="email" label="Email address" type="email" placeholder="you@school.test" required autofocus />
            <x-button type="submit" variant="primary" icon="mail" class="btn-block" style="min-height:44px">Send reset link</x-button>
        </div>
    </form>

    <div class="auth-links">
        <a href="{{ route('login') }}"><x-icon name="arrow-left" :size="12" style="vertical-align:-2px" /> Back to sign in</a>
    </div>
@endsection
