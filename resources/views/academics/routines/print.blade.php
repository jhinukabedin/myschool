<!doctype html>
<html lang="{{ str_replace('_', '-', $documentLocale ?? app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $routine->class?->name }} - {{ $routine->section?->name }} · {{ __('Class Routine') }}</title>
    <link rel="preload" href="{{ $routineFontRegular }}" as="font" type="font/ttf" crossorigin>
    <style>
        @font-face { font-family: NotoRoutine; font-style: normal; font-weight: 400; src: url("{{ $routineFontRegular }}") format("truetype"); }
        @font-face { font-family: NotoRoutine; font-style: normal; font-weight: 700; src: url("{{ $routineFontBold }}") format("truetype"); }
        @page { size: A4 landscape; margin: 8mm; }
        html, body { min-height:100%; margin:0; padding:0; }
        body { background:#edf1f7; color:#172b4d; font-family:NotoRoutine,"Noto Sans Bengali",sans-serif; }
        .routine-print-toolbar { position:sticky;top:0;z-index:5;display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px max(18px,calc((100vw - 1180px)/2));background:#172b4d;color:#fff;box-shadow:0 3px 14px rgba(15,35,65,.15); }
        .routine-print-toolbar a,.routine-print-toolbar button { display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:8px 14px;border:1px solid rgba(255,255,255,.32);border-radius:7px;background:transparent;color:#fff;font:700 13px NotoRoutine,"Noto Sans Bengali",sans-serif;text-decoration:none;cursor:pointer; }
        .routine-print-toolbar .routine-print-primary { border-color:#8061e8;background:#7351db; }
        .routine-print-actions { display:flex;gap:8px; }
        .routine-document { margin:22px auto;background:#fff; }
        @if ($isEmbedded)
            .routine-print-toolbar { display:none !important; }
            .routine-document { margin:0 auto;background:#fff; }
        @endif
        @media print {
            html,body { min-height:0;background:#fff; }
            .routine-print-toolbar { display:none !important; }
            .routine-document { max-width:none;margin:0;background:#fff; }
        }
    </style>
</head>
<body>
    <nav class="routine-print-toolbar" aria-label="{{ __('Routine actions', [], $documentLocale ?? app()->getLocale()) }}">
        @unless ($isEmbedded)
            <a href="{{ $backUrl }}">{{ __('Go back', [], $documentLocale ?? app()->getLocale()) }}</a>
        @endunless
        <div class="routine-print-actions">
            <a href="{{ $pdfDownloadUrl }}">{{ __('Download PDF', [], $documentLocale ?? app()->getLocale()) }}</a>
            <button class="routine-print-primary" type="button" onclick="window.print()">{{ __('Print', [], $documentLocale ?? app()->getLocale()) }}</button>
        </div>
    </nav>
    @include('academics.routines._document', [
        'routineFontFamily' => 'NotoRoutine',
        'routineFontRegular' => $routineFontRegular,
        'routineFontBold' => $routineFontBold,
        'routineLogo' => $routineLogo,
        'routineLogoMark' => $routineLogoMark,
        'routineEstablishedYear' => $routineEstablishedYear,
        'documentLocale' => $documentLocale ?? null,
    ])
    @include('layouts.partials.print-developer-footer')
</body>
</html>
