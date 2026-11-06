<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0b0716">
    <meta name="color-scheme" content="dark light">
    <meta name="turbo-prefetch" content="true">
    <meta name="turbo-cache-control" content="no-cache">
    <meta name="view-transition" content="same-origin">
    <title>@yield('title', __('Sign in')) — {{ $schoolSettings['name'] ?? config('app.name') }}</title>

    @if (app()->getLocale() === 'bn')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Noto+Sans+Bengali:wght@400;500;600;700&display=swap" rel="stylesheet">
    @endif

    <script>
        try {
            if (localStorage.getItem('theme') === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        } catch (e) {}
    </script>

    <?php $interfaceStrings = [
            'locale' => app()->getLocale(),
            'switchToDark' => __('Switch to dark theme'),
            'switchToLight' => __('Switch to light theme'),
        ]; ?>

    <script>
        window.__i18n = @json($interfaceStrings);
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="locale-{{ app()->getLocale() }}">
    <div class="auth-shell">
        <section class="auth-brand">
            <div class="auth-brand-content">
                <div style="display:flex;align-items:center;gap:12px">
                    <div class="brand-mark" style="width:48px;height:48px;border-radius:15px"><x-icon name="graduation-cap" :size="26" /></div>
                    <div>
                        <div style="font-size:17px;font-weight:700">{{ $schoolSettings['name'] ?? 'EduSphere' }}</div>
                        <div class="brand-sub" style="margin-top:5px">{{ __('School Management System') }}</div>
                    </div>
                </div>

                <h1>{{ __('Every student, every fee, every result — in one place.') }}</h1>
                <p>{{ __('A modern operating system for your school. Manage admissions, fees, attendance, examinations, payroll and communication with a complete audit trail.') }}</p>

                <div class="auth-features">
                    <div class="auth-feature"><span class="tick"><x-icon name="check" :size="12" /></span> {{ __('Real-time fee collection, dues and printable receipts') }}</div>
                    <div class="auth-feature"><span class="tick"><x-icon name="check" :size="12" /></span> {{ __('Attendance, marks, GPA and merit lists') }}</div>
                    <div class="auth-feature"><span class="tick"><x-icon name="check" :size="12" /></span> {{ __('Staff payroll with salary slips and reports') }}</div>
                    <div class="auth-feature"><span class="tick"><x-icon name="check" :size="12" /></span> {{ __('Granular permissions and audited financial actions') }}</div>
                </div>
            </div>

            <div class="auth-brand-content" style="font-size:11px;color:var(--muted-2)">
                {{ $schoolSettings['address'] ?? '' }}
                @if (! empty($schoolSettings['phone']))
                    <span style="display:block;margin-top:6px">{{ $schoolSettings['phone'] }} · {{ $schoolSettings['email'] ?? '' }}</span>
                @endif
            </div>
        </section>

        <section class="auth-form-side">
            <div class="auth-controls">
                <div class="lang-switch" role="group" aria-label="{{ __('Language') }}">
                    @foreach (config('app.available_locales', ['en' => 'English']) as $code => $label)
                        <a
                            href="{{ route('language.switch', $code) }}"
                            class="lang-option {{ app()->getLocale() === $code ? 'active' : '' }}"
                            hreflang="{{ $code }}"
                            @if (app()->getLocale() === $code) aria-current="true" @endif
                            title="{{ $label }}"
                        >{{ $code === 'bn' ? 'বাংলা' : strtoupper($code) }}</a>
                    @endforeach
                </div>

                <button type="button" class="icon-btn theme-toggle" onclick="window.toggleTheme && window.toggleTheme()" aria-label="{{ __('Toggle theme') }}" title="{{ __('Toggle theme') }}">
                    <x-icon name="sun" :size="17" class="theme-icon theme-icon-sun" />
                    <x-icon name="moon" :size="17" class="theme-icon theme-icon-moon" />
                </button>
            </div>

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
                    &copy; {{ now()->year }} {{ $schoolSettings['name'] ?? config('app.name') }}. {{ __('All rights reserved.') }}
                </div>
            </div>
        </section>
    </div>
</body>
</html>
