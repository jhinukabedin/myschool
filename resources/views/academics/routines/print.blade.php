@extends('layouts.print')

@section('title', 'Class Routine - '.($routine->class?->name ?? 'Class').' Section '.($routine->section?->name ?? ''))

@push('head')
<style>
    @page {
        size: A4 landscape;
        margin: 8mm;
    }

    .routine-print-sheet {
        background: #fff;
        color: #111;
        max-width: 1120px;
        margin: 0 auto;
        padding: 18px 22px 22px;
    }

    .routine-print-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        padding-bottom: 12px;
        border-bottom: 2px solid #111;
    }

    .routine-print-brand {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .routine-print-logo {
        width: 54px;
        height: 54px;
        border-radius: 10px;
        border: 1px solid #bbb;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 18px;
        letter-spacing: 0.04em;
        background: #f5f3ff;
        overflow: hidden;
    }

    .routine-print-logo img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .routine-print-school {
        font-size: 21px;
        font-weight: 800;
        line-height: 1.15;
    }

    .routine-print-sub {
        font-size: 11px;
        color: #444;
        margin-top: 2px;
    }

    .routine-print-badge {
        text-align: right;
        min-width: 190px;
    }

    .routine-print-badge .title {
        font-size: 15px;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .routine-print-meta {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 8px 18px;
        margin: 12px 0 16px;
        font-size: 11px;
    }

    .routine-print-meta span {
        display: block;
        color: #555;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        font-size: 9px;
        font-weight: 700;
    }

    .routine-print-meta strong {
        font-size: 13px;
    }

    .routine-print-sheet .routine-table-wrap {
        overflow: visible;
    }

    .routine-print-sheet .routine-table {
        font-size: 10px;
    }

    .routine-print-sheet .routine-table th,
    .routine-print-sheet .routine-table td {
        padding: 5px 4px;
    }

    .routine-print-sheet .routine-table thead th {
        font-size: 9px;
    }

    .routine-print-sheet .routine-cell-subject {
        font-size: 10px;
    }

    .routine-print-sheet .routine-cell-meta,
    .routine-print-sheet .routine-cell-room {
        font-size: 8.5px;
    }

    .routine-print-foot {
        display: flex;
        justify-content: space-between;
        gap: 18px;
        margin-top: 16px;
        padding-top: 10px;
        border-top: 1px solid #bbb;
        font-size: 9.5px;
        color: #555;
    }

    @media print {
        .routine-print-sheet {
            padding: 0;
            max-width: none;
        }

        .routine-print-foot {
            page-break-inside: avoid;
        }
    }
</style>
@endpush

@section('content')
    <div class="routine-print-sheet">
        <div class="routine-print-head">
            <div class="routine-print-brand">
                <div class="routine-print-logo">
                    @if (school_setting('website_logo_path'))
                        <img src="{{ asset('storage/'.ltrim(school_setting('website_logo_path'), '/')) }}" alt="{{ school_name() }} logo">
                    @else
                        {{ school_setting('website_logo_mark', 'HS') }}
                    @endif
                </div>
                <div>
                    <div class="routine-print-school">{{ school_name() }}</div>
                    <div class="routine-print-sub">{{ school_setting('school_address') }}</div>
                    <div class="routine-print-sub">
                        {{ school_setting('school_phone') }}@if (school_setting('school_email')) &middot; {{ school_setting('school_email') }} @endif
                    </div>
                </div>
            </div>
            <div class="routine-print-badge">
                <div class="title">Class Routine</div>
                <div class="routine-print-sub">Weekly timetable</div>
                <div class="routine-print-sub">Printed {{ now()->format('d M Y, H:i') }}</div>
            </div>
        </div>

        <div class="routine-print-meta">
            <div><span>Academic year</span><strong>{{ $routine->academicSession?->name ?? '-' }}</strong></div>
            <div><span>Class</span><strong>{{ $routine->class?->name ?? '-' }}</strong></div>
            <div><span>Section</span><strong>{{ $routine->section?->name ?? '-' }}</strong></div>
            <div><span>Effective from</span><strong>{{ $routine->effectiveDate()->format('d M Y') }}</strong></div>
        </div>

        @include('academics.routines._timetable', [
            'editable' => false,
            'teacherView' => false,
            'print' => true,
        ])

        <div class="routine-print-foot">
            <span>Days run down the rows and time slots run across the columns. Lunch and break periods appear in every day row.</span>
            <span>{{ $routine->class?->name }} &middot; Section {{ $routine->section?->name }} &middot; {{ $routine->academicSession?->name }}</span>
        </div>
    </div>

    @if ($autoPrint)
        <script>
            window.addEventListener('load', function () {
                window.print();
            });
        </script>
    @endif
@endsection
