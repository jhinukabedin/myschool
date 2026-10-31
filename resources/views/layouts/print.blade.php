<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Print') — {{ $schoolSettings['name'] ?? config('app.name') }}</title>
    @vite(['resources/css/app.css'])
    @stack('head')
</head>
<body>
    <div class="print-page">
        <div class="print-hide" style="max-width:900px;margin:0 auto 12px;display:flex;justify-content:flex-end;gap:8px">
            <button type="button" class="btn btn-secondary" onclick="window.close()">Close</button>
            <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
        </div>
        @yield('content')
    </div>
</body>
</html>
