@extends('layouts.portal')

@section('title', 'My Profile')

@section('content')
    <div class="grid grid-2">
        <section class="card">
            <div class="card-head"><div><h2>{{ __('Employment details') }}</h2><p>{{ __('Maintained by the school office') }}</p></div></div>
            <div class="card-body">
                <div class="grid grid-2">
                    <div><div class="small muted">{{ __('Name') }}</div><strong>{{ $staff->full_name }}</strong></div>
                    <div><div class="small muted">{{ __('Employee no') }}</div><strong class="mono">{{ $staff->employee_no }}</strong></div>
                    <div><div class="small muted">{{ __('Department') }}</div><strong>{{ $staff->department?->name ?? '—' }}</strong></div>
                    <div><div class="small muted">{{ __('Designation') }}</div><strong>{{ $staff->designation?->name ?? '—' }}</strong></div>
                    <div><div class="small muted">{{ __('Category') }}</div><strong>{{ __($staff->category_label) }}</strong></div>
                    <div><div class="small muted">{{ __('Employment type') }}</div><strong>{{ ucfirst(str_replace('_', ' ', $staff->employment_type)) }}</strong></div>
                    <div><div class="small muted">{{ __('Joined') }}</div><strong>{{ $staff->joining_date?->format('d M Y') ?? '—' }}</strong></div>
                    <div><div class="small muted">{{ __('Basic salary') }}</div><strong>{{ money($staff->basic_salary) }}</strong></div>
                    <div><div class="small muted">{{ __('Status') }}</div><strong>{{ ucfirst($staff->status) }}</strong></div>
                </div>

                @if ($staff->qualifications->isNotEmpty())
                    <div style="margin-top:16px">
                        <div class="small muted" style="margin-bottom:6px">{{ __('Qualifications') }}</div>
                        <div class="stack" style="gap:6px">
                            @foreach ($staff->qualifications as $qualification)
                                <div style="font-size:12.5px">
                                    <strong>{{ $qualification->exam_name }}</strong>
                                    @if ($qualification->passing_year) · {{ $qualification->passing_year }}@endif
                                    @if ($qualification->institution) · {{ $qualification->institution }}@endif
                                    @if ($qualification->result) · {{ $qualification->result }}@endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </section>

        <section class="card">
            <div class="card-head"><div><h2>{{ __('Contact details') }}</h2><p>{{ __('You can update these yourself') }}</p></div></div>
            <form method="POST" action="{{ route('staff-portal.profile.update') }}" enctype="multipart/form-data">
                @csrf
                <div class="card-body">
                    <div class="stack">
                        <label class="field">
                            <span class="field-label">{{ __('Phone') }}<span class="required-mark">*</span></span>
                            <input class="input" type="text" name="phone" value="{{ old('phone', $staff->phone) }}" maxlength="30" required>
                            @error('phone')<span class="field-error">{{ $message }}</span>@enderror
                        </label>
                        <label class="field">
                            <span class="field-label">{{ __('Email') }}</span>
                            <input class="input" type="email" name="email" value="{{ old('email', $staff->email) }}" maxlength="255">
                            @error('email')<span class="field-error">{{ $message }}</span>@enderror
                        </label>
                        <label class="field">
                            <span class="field-label">{{ __('Address') }}</span>
                            <input class="input" type="text" name="address" value="{{ old('address', $staff->address) }}" maxlength="255">
                        </label>
                        <label class="field">
                            <span class="field-label">{{ __('Photo') }}</span>
                            <input class="input" type="file" name="photo" accept=".jpg,.jpeg,.png,.webp">
                            @if ($staff->photo_path)
                                <span class="field-hint">{{ __('Uploading a new photo replaces the current one.') }}</span>
                            @endif
                            @error('photo')<span class="field-error">{{ $message }}</span>@enderror
                        </label>
                    </div>
                </div>
                <div class="card-foot" style="display:flex;justify-content:flex-end;padding:0 18px 16px">
                    <button type="submit" class="btn btn-primary">{{ __('Save changes') }}</button>
                </div>
            </form>
        </section>
    </div>

    <section class="card" style="margin-top:16px">
        <div class="card-head"><div><h2>{{ __('Account') }}</h2><p>{{ __('Login and security') }}</p></div></div>
        <div class="card-body">
            <div class="grid grid-2">
                <div><div class="small muted">{{ __('Login username') }}</div><strong class="mono">{{ auth()->user()->username ?? '—' }}</strong></div>
                <div><div class="small muted">{{ __('Login email') }}</div><strong>{{ auth()->user()->email ?? '—' }}</strong></div>
                <div><div class="small muted">{{ __('Role') }}</div><strong>{{ auth()->user()->role?->name ?? '—' }}</strong></div>
                <div><div class="small muted">{{ __('Last sign-in') }}</div><strong>{{ auth()->user()->last_login_at?->format('d M Y, h:i A') ?? '—' }}</strong></div>
            </div>
            <p style="margin-top:16px"><a class="btn btn-secondary" href="{{ route('password.change') }}">{{ __('Change password') }}</a></p>
        </div>
    </section>
@endsection
