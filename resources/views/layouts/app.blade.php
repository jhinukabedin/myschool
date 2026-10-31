<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#150c2b">
    <title>@yield('title', 'Dashboard') — {{ $schoolSettings['name'] ?? config('app.name') }}</title>
    @php($dateCalendarSettings = app(\App\Services\DateCalendarService::class)->settings())
    @if ($dateCalendarSettings->enabled && $dateCalendarSettings->language === 'bangla')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;500;600&display=swap" rel="stylesheet">
    @endif
    <script>
        // Apply the saved sidebar state before the first paint to avoid a flicker.
        try {
            if (localStorage.getItem('sidebar-collapsed') === '1') {
                document.documentElement.classList.add('sidebar-collapsed');
            }
        } catch (e) {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body x-data="{ sidebarOpen: false }">
    <a href="#main-content" class="btn btn-primary" style="position:absolute;left:-9999px;top:8px;z-index:999" onfocus="this.style.left='8px'" onblur="this.style.left='-9999px'">Skip to content</a>

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
                            <div class="alert-title">Please correct the highlighted fields</div>
                            <span>{{ $errors->count() }} validation {{ Str::plural('issue', $errors->count()) }} need your attention.</span>
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

    @stack('modals')
    @stack('scripts')
</body>
</html>
