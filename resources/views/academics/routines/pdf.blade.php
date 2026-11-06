<!doctype html>
<html lang="{{ str_replace('_', '-', $documentLocale ?? app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $routine->class?->name }} - {{ $routine->section?->name }} {{ __('routine') }}</title>
</head>
<body>
    window.print()
    @include('academics.routines._document', ['routineFontFamily' => 'RoutineBengali', 'documentLocale' => $documentLocale ?? null])
    @include('layouts.partials.print-developer-footer')
</body>
</html>
