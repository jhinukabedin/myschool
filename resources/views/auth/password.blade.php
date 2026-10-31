@extends('layouts.app')

@section('title', 'Password Change')
@section('breadcrumb', 'Account')

@section('content')
    <x-page-header title="Password Change" subtitle="Keep your account secure with a strong, unique password." />

    <div class="grid-side-main">
        <x-card title="Account details" subtitle="Signed-in administrator">
            <div class="detail-list">
                <div class="detail-row"><span class="detail-label">Name</span><span class="detail-value">{{ auth()->user()->name }}</span></div>
                <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value">{{ auth()->user()->email }}</span></div>
                <div class="detail-row"><span class="detail-label">Role</span><span class="detail-value">{{ auth()->user()->role?->name ?? '—' }}</span></div>
                <div class="detail-row"><span class="detail-label">Last sign in</span><span class="detail-value">{{ auth()->user()->last_login_at?->format('d M Y, h:i A') ?? '—' }}</span></div>
            </div>
            <div class="info-box" style="margin-top:16px">
                Passwords are hashed with bcrypt. Sessions are protected by CSRF tokens and login throttling.
            </div>
        </x-card>

        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Update password</h3>
                    <p class="card-sub">You will remain signed in on this device.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                @method('PUT')
                <div class="form-section">
                    <div class="form-grid">
                        <div class="span-2">
                            <x-input name="current_password" label="Current password" type="password" required autocomplete="current-password" />
                        </div>
                        <x-input name="password" label="New password" type="password" required autocomplete="new-password" help="Minimum 8 characters." />
                        <x-input name="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password" />
                    </div>
                </div>
                <div class="form-section" style="display:flex;justify-content:flex-end">
                    <x-button type="submit" variant="primary" icon="save">Update password</x-button>
                </div>
            </form>
        </div>
    </div>
@endsection
