@php
    $routineFontFamily = $routineFontFamily ?? 'NotoRoutine';
    $routineFontRegular = $routineFontRegular ?? null;
    $routineFontBold = $routineFontBold ?? null;
    $routineLogo = $routineLogo ?? null;
    $routineLogoMark = $routineLogoMark ?? 'HS';
    $routineEstablishedYear = $routineEstablishedYear ?? null;
    $documentLocale = $documentLocale ?? app()->getLocale();
    $tr = fn (string $key) => __($key, [], $documentLocale);
@endphp
<style>
    @page { size: A4 landscape; margin: 8mm 8mm 18mm; }
    @if ($routineFontRegular && $routineFontBold)
    @font-face { font-family: NotoRoutine; font-style: normal; font-weight: 400; src: url("{{ $routineFontRegular }}") format("truetype"); }
    @font-face { font-family: NotoRoutine; font-style: normal; font-weight: 700; src: url("{{ $routineFontBold }}") format("truetype"); }
    @endif
    .routine-document, .routine-document * { box-sizing: border-box; }
    .routine-document { width: 100%; max-width: 1180px; margin: 0 auto; padding: 0; background: #fff; color: #172b4d; font-family: {{ $routineFontFamily }}, "Noto Sans Bengali", sans-serif; font-size: 10px; line-height: 1.4; }
    .routine-document .routine-heading { width: 100%; padding: 0 0 10px; border-bottom: 2px solid #264777; }
    .routine-document .routine-brand-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .routine-document .routine-brand-table td { border: 0; padding: 0; vertical-align: middle; }
    .routine-document .routine-brand-table .routine-school-cell { text-align: left; }
    .routine-document .routine-logo-cell { width: 6%; padding-right: 10px !important; }
    .routine-document .routine-school-cell { width: 52%; }
    .routine-document .routine-logo { display: block; width: 54px; height: 54px; object-fit: contain; }
    .routine-document .routine-logo-mark { display: block; width: 48px; height: 48px; border: 1px solid #cbd6e5; border-radius: 50%; color: #264777; font-size: 16px; font-weight: 700; line-height: 48px; text-align: center; }
    .routine-document .routine-school { margin: 0; color: #17345f; font-size: 19px; font-weight: 700; line-height: 1.25; text-align: left !important; }
    .routine-document .routine-established { margin: 3px 0 0; color: #61718a; font-size: 9px; text-align: left !important; }
    .routine-document .routine-contact-cell { width: 42%; padding-left: 14px !important; text-align: right; }
    .routine-document .routine-contact { margin: 2px 0 0; color: #52647e; font-size: 8.5px; line-height: 1.35; }
    .routine-document .routine-contact a { color: #264777; text-decoration: none; }
    .routine-document .routine-title { margin: 10px 0 8px; color: #17345f; font-size: 15px; font-weight: 700; text-align: center; }
    .routine-document .routine-meta { width: 100%; margin: 0 0 9px; border-collapse: collapse; table-layout: fixed; }
    .routine-document .routine-meta td { width: 25%; padding: 6px 4px; border: 1px solid #d4deeb; background: #f3f6fb; text-align: center; vertical-align: middle; }
    .routine-document .meta-label { display: block; margin-bottom: 2px; color: #63728a; font-size: 8px; }
    .routine-document .meta-value { display: block; color: #1d365d; font-size: 10px; font-weight: 700; }
    .routine-document .routine-table-wrap { width: 100%; overflow: visible; }
    .routine-document .routine-table { width: 100%; table-layout: fixed; border-collapse: collapse; text-align: center; }
    .routine-document .routine-table th, .routine-document .routine-table td { padding: 5px 3px; border: 1px solid #aab8cc; color: #263954; text-align: center; vertical-align: middle; line-height: 1.35; }
    .routine-document .routine-table thead th { background: #e4ebf6; color: #10294e; font-size: 11px; font-weight: 700; }
    .routine-document .routine-table th:first-child, .routine-document .routine-table td:first-child { width: 72px; }
    .routine-document .routine-table tbody th { background: #f3f6fb; color: #19365f; font-size: 9px; font-weight: 700; }
    .routine-document .routine-time { display: block; margin-bottom: 2px; color: #435a7a; font-size: 8.5px; font-weight: 500; }
    .routine-document .routine-subject { display: block; color: #1c3559; font-size: 9px; font-weight: 700; }
    .routine-document .routine-teacher, .routine-document .routine-room { display: block; margin-top: 1px; color: #596b84; font-size: 7.5px; font-weight: 400; }
    .routine-document .routine-break { background: #fff7e8; color: #8a5b05 !important; font-weight: 700; }
    .routine-document .routine-break .routine-time { color: #927847; }
    .routine-document .routine-empty { color: #9aa8bb; }
    .routine-document .routine-footer { margin-top: 9px; padding-top: 6px; border-top: 1px solid #d4deeb; color: #66758b; font-size: 8px; text-align: right; }
    .routine-document .routine-organization { page-break-inside: avoid; }
    .report-print-footer { display:flex; justify-content:space-between; align-items:center; gap:12px; width:100%; margin:10px auto 0; padding:5px 0 0; border-top:1px solid #d4deeb; color:#66758b; font:8px/1.35 {{ $routineFontFamily }},"Noto Sans Bengali",sans-serif; }
    .report-print-footer strong { color:#17345f; }
    .report-print-footer a { color:#264777; text-decoration:none; }
    .report-print-footer-contact { display:flex; gap:10px; }
    @media print {
        @page { margin-bottom:18mm; }
        .routine-document { max-width: none; }
        .routine-document .routine-table thead { display: table-header-group; }
        .routine-document .routine-table tr { page-break-inside: avoid; }
        .routine-document .routine-meta td, .routine-document .routine-table th, .routine-document .routine-table td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .report-print-footer { position:fixed; right:0; bottom:-12mm; left:0; width:100%; margin:0; background:#fff; }
        .report-print-footer-pages::after { content:"Page " counter(page) " of " counter(pages); }
    }
</style>

<section class="routine-document">
    <header class="routine-heading">
        <table class="routine-brand-table"><tbody><tr>
            <td class="routine-logo-cell">
                @if ($routineLogo)<img class="routine-logo" src="{{ $routineLogo }}" alt="School logo">
                @else <span class="routine-logo-mark">{{ $routineLogoMark }}</span>@endif
            </td>
            <td class="routine-school-cell">
                <h1 class="routine-school">{{ school_name() }}</h1>
                @if ($routineEstablishedYear)<p class="routine-established">{{ $tr('Established') }} {{ $routineEstablishedYear }}</p>@endif
            </td>
            <td class="routine-contact-cell">
                @if (school_setting('school_address'))<p class="routine-contact">{{ school_setting('school_address') }}</p>@endif
                @if (school_setting('school_email'))<p class="routine-contact">{{ school_setting('school_email') }}</p>@endif
                @if (school_setting('school_website'))<p class="routine-contact">{{ school_setting('school_website') }}</p>@endif
                @if (school_setting('school_phone'))<p class="routine-contact">{{ school_setting('school_phone') }}</p>@endif
            </td>
        </tr></tbody></table>
    </header>
    <h2 class="routine-title">{{ $tr('Weekly Class Routine') }}</h2>
    <table class="routine-meta"><tbody><tr>
        <td><span class="meta-label">{{ $tr('Academic year') }}</span><span class="meta-value">{{ $routine->academicSession?->name ?? '-' }}</span></td>
        <td><span class="meta-label">{{ $tr('Class') }}</span><span class="meta-value">{{ $routine->class?->name ?? '-' }}</span></td>
        <td><span class="meta-label">{{ $tr('Section') }}</span><span class="meta-value">{{ $routine->section?->name ?? '-' }}</span></td>
        <td><span class="meta-label">{{ $tr('Effective') }}</span><span class="meta-value">{{ $routine->effective_from?->format('d M Y') ?? '-' }}</span></td>
    </tr></tbody></table>
    <div class="routine-table-wrap">
        <table class="routine-table">
            <thead><tr><th class="routine-axis-label">{{ $tr('Day') }}</th>
                @foreach ($slots as $slot)
                    <th><span class="routine-time">{{ $slot->time_range }}</span><span class="routine-slot-name">{{ $slot->name }}</span></th>
                @endforeach
            </tr></thead>
            <tbody>
                @foreach ($days as $dayKey => $dayLabel)
                    <tr><th class="routine-day-col" scope="row">{{ $tr($dayLabel) }}</th>
                        @foreach ($slots as $slot)
                            @php($entry = $entryMap->get($dayKey.'|'.$slot->id))
                            @if (! $slot->isTeaching())
                                <td class="routine-break"><span class="routine-time">{{ $slot->time_range }}</span>{{ $slot->break_label }}</td>
                            @elseif ($entry)
                                <td><span class="routine-subject">{{ $entry->subject?->name ?? '-' }}</span>
                                    @if ($entry->teacher?->full_name)<span class="routine-teacher">{{ $entry->teacher->full_name }}</span>@endif
                                    @if ($entry->classroom)<span class="routine-room">{{ $entry->classroom }}</span>@endif
                                </td>
                            @else
                                <td class="routine-empty">—</td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <footer class="routine-footer">{{ school_name() }} · {{ now()->format('d M Y') }}</footer>
</section>
