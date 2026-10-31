<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Sign in') — {{ $schoolSettings['name'] ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="auth-shell">
        <section class="auth-brand">
            <div class="auth-brand-content">
                <div style="display:flex;align-items:center;gap:12px">
                    <div class="brand-mark" style="width:48px;height:48px;border-radius:15px"><x-icon name="graduation-cap" :size="26" /></div>
                    <div>
                        <div style="font-size:17px;font-weight:700">{{ $schoolSettings['name'] ?? 'EduSphere' }}</div>
                        <div class="brand-sub" style="margin-top:5px">School Management System</div>
                    </div>
                </div>

                <h1>Every student, every fee, every result — in one place.</h1>
                <p>A modern operating system for your school. Manage admissions, fees, attendance, examinations, payroll and communication with a complete audit trail.</p>

                <div class="auth-features">
                    <div class="auth-feature"><span class="tick"><x-icon name="check" :size="12" /></span> Real-time fee collection, dues and printable receipts</div>
                    <div class="auth-feature"><span class="tick"><x-icon name="check" :size="12" /></span> Attendance, marks, GPA and merit lists</div>
                    <div class="auth-feature"><span class="tick"><x-icon name="check" :size="12" /></span> Staff payroll with salary slips and reports</div>
                    <div class="auth-feature"><span class="tick"><x-icon name="check" :size="12" /></span> Granular permissions and audited financial actions</div>
                </div>
            </div>

            <div class="auth-brand-content" style="font-size:11px;color:#7c6f9e">
                {{ $schoolSettings['address'] ?? '' }}
                @if (! empty($schoolSettings['phone']))
                    <span style="display:block;margin-top:6px">{{ $schoolSettings['phone'] }} · {{ $schoolSettings['email'] ?? '' }}</span>
                @endif
            </div>
        </section>

        <section class="auth-form-side">
            <div class="auth-card">
                <div style="display:flex;align-items:center;gap:9px;margin-bottom:24px">
                    <div class="brand-mark" style="width:36px;height:36px;border-radius:11px"><x-icon name="school" :size="19" /></div>
                    <span style="font-size:13px;font-weight:650">{{ $schoolSettings['name'] ?? config('app.name') }}</span>
                </div>

                @if (session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <span class="alert-icon"><x-icon name="alert-circle" :size="16" /></span>
                        <div>{{ $errors->first() }}</div>
                    </div>
                @endif

                @yield('content')

                <div class="auth-footer">
                    &copy; {{ now()->year }} {{ $schoolSettings['name'] ?? config('app.name') }}. All rights reserved.
                </div>
            </div>
        </section>
    </div>
</body>
</html>
