<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0b0716">
    <meta name="color-scheme" content="dark light">

    {{-- Turbo Drive: instant, reload-free navigation with hover prefetching. --}}
    <meta name="turbo-prefetch" content="true">
    <meta name="turbo-cache-control" content="no-cache">
    <meta name="view-transition" content="same-origin">

    <title>@yield('title', __('Dashboard')) — {{ $schoolSettings['name'] ?? config('app.name') }}</title>

    @php($dateCalendarSettings = app(\App\Services\DateCalendarService::class)->settings())
    <?php
        $googleFonts = ['Hind Siliguri', 'Noto Sans Bengali', 'Noto Serif Bengali', 'Baloo Da 2', 'Anek Bangla', 'Inter', 'Roboto', 'Poppins', 'Lato', 'Open Sans', 'Montserrat'];
        $fontBn = (string) school_setting('font_bn', 'Hind Siliguri');
        $fontEn = (string) school_setting('font_en', 'Segoe UI');
        $customFontPath = (string) school_setting('custom_font_path', '');
        if ($customFontPath !== '') {
            $fontBn = 'CustomFont';
        }
        $fontsToLoad = array_values(array_unique(array_filter([$fontBn, $fontEn], fn ($f) => in_array($f, $googleFonts, true))));
        $fontQuery = implode('&family=', array_map(fn ($f) => str_replace(' ', '+', $f).':wght@400;500;600;700', $fontsToLoad));
    ?>

    @if ($customFontPath !== '')
        <style>
            @font-face {
                font-family: 'CustomFont';
                src: url('{{ asset($customFontPath) }}') format('truetype');
                font-weight: 400;
                font-style: normal;
            }
        </style>
    @endif

    @if ($fontQuery !== '')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family={{ $fontQuery }}&display=swap" rel="stylesheet">
    @endif

    <style>
        :root {
            --font-bn: '{{ $fontBn }}', 'Hind Siliguri', 'Noto Sans Bengali', sans-serif;
            --font-en: '{{ $fontEn }}', 'Segoe UI', 'Inter', sans-serif;
        }
        body { font-family: var(--font-en); }
        body.locale-bn { font-family: var(--font-bn); }
        body.locale-bn .brand-name, body.locale-bn .topbar-title, body.locale-bn .page-title,
        body.locale-bn .card-title, body.locale-bn .stat-value, body.locale-bn .btn,
        body.locale-bn .sidebar-link { font-family: var(--font-bn); }
    </style>

    <script>
        // Apply saved interface preferences before the first paint.
        try {
            if (localStorage.getItem('sidebar-collapsed') === '1') {
                document.documentElement.classList.add('sidebar-collapsed');
            }
            if (localStorage.getItem('theme') === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        } catch (e) {}
    </script>

    <?php $interfaceStrings = [
            'locale' => app()->getLocale(),
            'confirmDeleteTitle' => __('Are you sure you want to delete?'),
            'confirmDeleteBody' => __('This action may permanently remove data.'),
            'confirmTitle' => __('Please confirm this action'),
            'confirmBody' => __('Please review this action before continuing.'),
            'confirmDelete' => __('Yes, delete'),
            'confirmContinue' => __('Continue'),
            'cancel' => __('Cancel'),
            'hideSidebar' => __('Hide sidebar'),
            'showSidebar' => __('Show sidebar'),
            'palettePlaceholder' => __('Search pages, actions and settings…'),
            'paletteEmpty' => __('No matching pages'),
            'palettePages' => __('Pages'),
            'paletteActions' => __('Quick actions'),
            'paletteHint' => __('Type to filter'),
            'paletteNavigate' => __('to navigate'),
            'paletteOpen' => __('to open'),
            'paletteClose' => __('to close'),
            'switchToDark' => __('Switch to dark theme'),
            'switchToLight' => __('Switch to light theme'),
            'loading' => __('Loading…'),
        ]; ?>

    <script>
        window.__i18n = @json($interfaceStrings);
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="locale-{{ app()->getLocale() }}" x-data="{ sidebarOpen: false }">
    <a href="#main-content" class="skip-link">{{ __('Skip to content') }}</a>

    @include('layouts.partials.impersonation-banner')

    <div class="app-shell">
        <div class="sidebar-overlay" x-show="sidebarOpen" x-cloak x-transition.opacity @click="sidebarOpen = false"></div>

        @include('layouts.partials.sidebar')

        <div class="main-wrap">
            @include('layouts.partials.topbar')

            <main class="page" id="main-content">
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <span class="alert-icon"><x-icon name="alert-triangle" :size="17" /></span>
                        <div>
                            <div class="alert-title">{{ __('Please correct the highlighted fields') }}</div>
                            <span>{{ $errors->count() === 1 ? __(':count validation issue needs your attention', ['count' => $errors->count()]) : __(':count validation issues need your attention', ['count' => $errors->count()]) }}</span>
                        </div>
                    </div>
                @endif

                @if (session('success'))
                    <div class="alert alert-success" role="status">
                        <span class="alert-icon"><x-icon name="check-circle" :size="17" /></span>
                        <div>{{ session('success') }}</div>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger" role="alert">
                        <span class="alert-icon"><x-icon name="alert-circle" :size="17" /></span>
                        <div>{{ session('error') }}</div>
                    </div>
                @endif

                @if (session('warning'))
                    <div class="alert alert-warning" role="alert">
                        <span class="alert-icon"><x-icon name="alert-triangle" :size="17" /></span>
                        <div>{{ session('warning') }}</div>
                    </div>
                @endif

                @if (session('status'))
                    <div class="alert alert-info" role="status">
                        <span class="alert-icon"><x-icon name="info" :size="17" /></span>
                        <div>{{ session('status') }}</div>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    @include('layouts.partials.command-palette')
    @include('layouts.partials.print-developer-footer')
    @stack('modals')
    @stack('scripts')
</body>
</html>
