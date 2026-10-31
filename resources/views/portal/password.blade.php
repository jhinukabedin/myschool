@extends('layouts.portal')

@section('title', 'Change Password')
@section('banner-subtitle', 'আপনার Student Portal নিরাপদ রাখুন')

@section('content')
    <div class="portal-panels">
        <div class="portal-panel" style="max-width:650px">
            <h2>পাসওয়ার্ড পরিবর্তন</h2>
            <div class="portal-panel-body">
                <div class="portal-note"><strong>Change password</strong> — নতুন পাসওয়ার্ড কমপক্ষে ৮ অক্ষরের রাখুন এবং কারও সাথে শেয়ার করবেন না।</div>
                <form method="POST" action="{{ route('password.update') }}" style="margin-top:16px">
                    @csrf
                    @method('PUT')
                    <div class="field">
                        <label for="current_password">বর্তমান পাসওয়ার্ড</label>
                        <input id="current_password" name="current_password" type="password" required autocomplete="current-password">
                        @error('current_password')<small style="color:#b42318">{{ $message }}</small>@enderror
                    </div>
                    <div class="field" style="margin-top:12px">
                        <label for="password">নতুন পাসওয়ার্ড</label>
                        <input id="password" name="password" type="password" required autocomplete="new-password">
                        @error('password')<small style="color:#b42318">{{ $message }}</small>@enderror
                    </div>
                    <div class="field" style="margin-top:12px">
                        <label for="password_confirmation">আবার লিখুন</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                    </div>
                    <button class="btn btn-purple" type="submit" style="margin-top:15px">আপডেট করুন</button>
                </form>
            </div>
        </div>

        <div class="portal-panel">
            <h2>অ্যাকাউন্ট</h2>
            <div class="portal-panel-body">
                <div class="info-list" style="grid-template-columns:1fr">
                    <div><b>Name</b><span>{{ auth()->user()->name }}</span></div>
                    <div><b>Username / student ID</b><span class="mono">{{ auth()->user()->username ?? '—' }}</span></div>
                    <div><b>Mobile</b><span>{{ auth()->user()->phone ?? '—' }}</span></div>
                    <div><b>Role</b><span>{{ auth()->user()->role?->name ?? '—' }}</span></div>
                </div>
                <div class="portal-note" style="margin-top:14px">Never share your password. The school office will never ask for it.</div>
            </div>
        </div>
    </div>
@endsection
