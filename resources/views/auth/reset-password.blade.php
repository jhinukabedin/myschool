@extends('layouts.auth')

@section('title', 'Choose a new password')

@section('content')
    <h2>Choose a new password</h2>
    <p class="lead">Use at least 8 characters and avoid reusing your previous password.</p>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="stack" style="gap:14px">
            <x-input name="email" label="Email address" type="email" :value="$request->email" required autocomplete="username" />
            <x-input name="password" label="New password" type="password" required autocomplete="new-password" />
            <x-input name="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password" />
            <x-button type="submit" variant="primary" icon="key" class="btn-block" style="min-height:44px">Reset password</x-button>
        </div>
    </form>
@endsection
