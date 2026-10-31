@extends('layouts.app')

@section('title', 'My Class Schedule')

@section('content')
    <x-page-header eyebrow="Teacher" title="My Class Schedule"
        :subtitle="$session ? 'Your assigned periods for the '.$session->name.' session. Read-only: the academic office maintains the timetable.' : 'No academic session is configured.'">
        <x-slot:actions>
            <x-button :href="route('teacher.dashboard')" variant="secondary" icon="arrow-left">My teaching</x-button>
            @can('routines.view')
                <x-button :href="route('routines.index')" variant="secondary" icon="clipboard-list">All routines</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if (! $staff)
        <div class="card">
            <div class="card-body">
                <div class="warning-box">
                    Your account is not linked to a staff profile, so no timetable can be shown. Ask an administrator to link your user account to a teacher record.
                </div>
            </div>
        </div>
    @else
        <div class="card" style="margin-bottom:16px">
            <div class="card-header">
                <div>
                    <h3 class="card-title">{{ $staff->full_name }}</h3>
                    <p class="card-sub">{{ $entries->count() }} scheduled period(s) in this view. Overlapping classes are blocked when a routine is saved.</p>
                </div>
                <form method="GET" action="{{ route('teacher.schedule') }}" class="filter-bar" style="margin:0">
                    <div class="field">
                        <label class="field-label" for="session_id">Academic year</label>
                        <select class="select" id="session_id" name="session_id">
                            @foreach ($sessions as $sessionOption)
                                <option value="{{ $sessionOption->id }}" @selected((string) $selectedSessionId === (string) $sessionOption->id)>{{ $sessionOption->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-button type="submit" variant="secondary" icon="filter">Show</x-button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Weekly schedule</h3>
                    <p class="card-sub">Days are rows and time slots are columns. Each cell lists the class, section and room.</p>
                </div>
                <div class="routine-legend">
                    <span><i class="routine-dot routine-dot-class"></i> Your period</span>
                    <span><i class="routine-dot routine-dot-break"></i> Break / lunch</span>
                </div>
            </div>

            @include('academics.routines._timetable', [
                'editable' => false,
                'teacherView' => true,
                'print' => false,
            ])
        </div>

        @if ($entries->isNotEmpty())
            <div class="card" style="margin-top:16px">
                <div class="card-header">
                    <div><h3 class="card-title">Period list</h3><p class="card-sub">Flat list of the same schedule, useful on a phone.</p></div>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr><th>Day</th><th>Time</th><th>Class</th><th>Section</th><th>Subject</th><th>Room</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($entries->sortBy([['day', 'asc'], ['timeSlot.sort_order', 'asc']]) as $entry)
                                <tr>
                                    <td class="cell-title">{{ ucfirst($entry->day) }}</td>
                                    <td class="mono">{{ $entry->timeSlot?->time_range }}</td>
                                    <td>{{ $entry->routine?->class?->name }}</td>
                                    <td>{{ $entry->routine?->section?->name }}</td>
                                    <td>{{ $entry->subject?->name }}</td>
                                    <td>{{ $entry->classroom ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
@endsection
