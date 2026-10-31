@extends('layouts.portal')

@section('title', 'Class Routine')
@section('banner-subtitle', 'Your official weekly timetable. Days run down the rows and periods run across the columns.')

@section('content')
    <div class="portal-panel">
        <h2>Weekly class routine</h2>
        <div class="portal-panel-body">
            <div class="chips" style="margin-bottom:14px">
                @if ($enrollment)
                    <span class="chip">{{ $enrollment->class?->name }}</span>
                    <span class="chip">Section {{ $enrollment->section?->name ?? '-' }}</span>
                @endif
                <span class="chip">{{ $session?->name ?? 'Current session' }}</span>
                @if ($routine)
                    <span class="chip">Effective {{ $routine->effectiveDate()->format('d M Y') }}</span>
                @endif
            </div>

            @if (! $routine)
                <div class="portal-empty">
                    <strong>No published class routine yet.</strong>
                    <p>The academic office has not published a structured timetable for your class and section. Please check back later.</p>
                </div>
            @elseif ($slots->isEmpty())
                <div class="portal-empty">
                    <strong>Timetable periods are not configured.</strong>
                    <p>Ask the academic office to configure the daily periods before the routine can be displayed.</p>
                </div>
            @else
                <div class="routine-table-wrap" role="region" aria-label="Class routine timetable" tabindex="0">
                    <table class="routine-table portal-routine-table">
                        <thead>
                            <tr>
                                <th class="routine-day-col" scope="col">Day</th>
                                @foreach ($slots as $slot)
                                    <th scope="col" @class(['routine-slot-break' => ! $slot->isTeaching()])>
                                        <span class="routine-slot-name">{{ $slot->name }}</span>
                                        <small class="routine-slot-time">{{ $slot->time_range }}</small>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($days as $dayKey => $dayLabel)
                                <tr>
                                    <th class="routine-day-col" scope="row">{{ $dayLabel }}</th>
                                    @foreach ($slots as $slot)
                                        @php($entry = $entryMap->get($dayKey.'|'.$slot->id))

                                        @if (! $slot->isTeaching())
                                            <td class="routine-break">
                                                <span>{{ $slot->break_label }}</span>
                                                <small>{{ $slot->time_range }}</small>
                                            </td>
                                        @else
                                            <td @class(['routine-cell', 'has-entry' => $entry])>
                                                @if ($entry)
                                                    <span class="routine-cell-subject">{{ $entry->subject?->name }}</span>
                                                    <span class="routine-cell-meta">{{ $entry->teacher?->full_name }}</span>
                                                    @if ($entry->classroom)
                                                        <span class="routine-cell-room">{{ $entry->classroom }}</span>
                                                    @endif
                                                @else
                                                    <span class="routine-empty">&mdash;</span>
                                                @endif
                                            </td>
                                        @endif
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="portal-routine-note">
                    Lunch and break periods appear in their own column in every day row. Contact the academic office if a period looks incorrect.
                </p>
            @endif
        </div>
    </div>
@endsection
