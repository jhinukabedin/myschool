@php
    $editable = $editable ?? false;
    $teacherView = $teacherView ?? false;
    $print = $print ?? false;
    $entryMap = $entryMap ?? collect();
@endphp

@if ($slots->isEmpty())
    <div class="info-box" style="margin:16px 20px">
        No active time slots are configured yet. An administrator can add periods under
        <strong>Class Routine &rarr; Days &amp; Time Slots</strong>.
    </div>
@else
    <div class="routine-table-wrap" role="region" aria-label="Class routine timetable" tabindex="0">
        <table @class(['routine-table', 'routine-table-print' => $print])>
            <thead>
                <tr>
                    <th class="routine-day-col" scope="col">
                        <span class="routine-axis-label">Day</span>
                        <small>Vertical rows</small>
                    </th>
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
                        <th class="routine-day-col" scope="row">
                            <span class="routine-day-name">{{ $dayLabel }}</span>
                        </th>
                        @foreach ($slots as $slot)
                            @php
                                $cellKey = $dayKey.'|'.$slot->id;
                                $entry = $entryMap->get($cellKey);
                            @endphp

                            @if (! $slot->isTeaching())
                                <td class="routine-break" data-day="{{ $dayKey }}" data-slot="{{ $slot->id }}">
                                    <span>{{ $slot->break_label }}</span>
                                    <small>{{ $slot->time_range }}</small>
                                </td>
                            @elseif ($teacherView)
                                <td @class(['routine-cell', 'has-entry' => $entry]) id="cell-{{ $dayKey }}-{{ $slot->id }}">
                                    @if ($entry)
                                        <span class="routine-cell-subject">{{ $entry->subject?->name }}</span>
                                        <span class="routine-cell-meta">
                                            {{ $entry->routine?->class?->name }}@if ($entry->routine?->section) &middot; Sec {{ $entry->routine->section->name }}@endif
                                        </span>
                                        @if ($entry->classroom)
                                            <span class="routine-cell-room">{{ $entry->classroom }}</span>
                                        @endif
                                    @else
                                        <span class="routine-empty">&mdash;</span>
                                    @endif
                                </td>
                            @elseif ($editable)
                                <td @class(['routine-cell', 'has-entry' => $entry]) id="cell-{{ $dayKey }}-{{ $slot->id }}">
                                    <button type="button"
                                        class="routine-cell-button"
                                        data-routine-cell
                                        data-day="{{ $dayKey }}"
                                        data-day-label="{{ $dayLabel }}"
                                        data-slot="{{ $slot->id }}"
                                        data-time="{{ $slot->time_range }}"
                                        title="{{ $entry ? 'Edit '.$entry->subject?->name : 'Assign a subject' }}">
                                        @if ($entry)
                                            <span class="routine-cell-subject">{{ $entry->subject?->name }}</span>
                                            <span class="routine-cell-meta">{{ $entry->teacher?->full_name }}</span>
                                            @if ($entry->classroom)
                                                <span class="routine-cell-room">{{ $entry->classroom }}</span>
                                            @endif
                                        @else
                                            <span class="routine-empty">+</span>
                                        @endif
                                    </button>
                                </td>
                            @else
                                <td @class(['routine-cell', 'has-entry' => $entry]) id="cell-{{ $dayKey }}-{{ $slot->id }}">
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
@endif
