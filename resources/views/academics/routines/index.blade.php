@extends('layouts.app')

@section('title', 'Class Routine')

@section('content')
    <x-page-header eyebrow="Academic management" title="Class Routine"
        subtitle="Build the official timetable with days as rows and time slots as columns. Every routine belongs to one academic year, class and section.">
        <x-slot:actions>
            @can('routines.edit')
                <x-button :href="route('routines.settings')" variant="secondary" icon="clock">Days &amp; time slots</x-button>
            @endcan
            @can('routines.create')
                <x-button :href="route('routines.create')" variant="primary" icon="plus">Create routine</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($slots->isEmpty())
        <div class="info-box" style="margin-bottom:16px">
            No active time slots are configured. Add periods under
            @can('routines.edit')<a href="{{ route('routines.settings') }}">Days &amp; time slots</a>@else Days &amp; time slots @endcan
            before creating a timetable.
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Routine manager</h3>
                <p class="card-sub">{{ $routines->total() }} routine(s), one per academic year, class and section</p>
            </div>
            <form method="GET" action="{{ route('routines.index') }}" class="filter-bar" style="margin:0;flex-wrap:wrap">
                <div class="field">
                    <label class="field-label" for="session_id">Academic year</label>
                    <select class="select" id="session_id" name="session_id">
                        <option value="">All years</option>
                        @foreach ($sessions as $session)
                            <option value="{{ $session->id }}" @selected((string) $selectedSessionId === (string) $session->id)>{{ $session->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="class_id">Class</label>
                    <select class="select" id="class_id" name="class_id">
                        <option value="">All classes</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" @selected(request('class_id') == $class->id)>{{ $class->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="section_id">Section</label>
                    <select class="select" id="section_id" name="section_id">
                        <option value="">All sections</option>
                        @foreach ($sections as $section)
                            <option value="{{ $section->id }}" @selected(request('section_id') == $section->id)>{{ $section->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-button type="submit" variant="secondary" icon="filter">Filter</x-button>
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Academic year</th>
                        <th>Class</th>
                        <th>Section</th>
                        <th>Effective from</th>
                        <th class="num">Periods</th>
                        <th>Updated</th>
                        <th class="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($routines as $routine)
                        <tr>
                            <td>{{ $routine->academicSession?->name }}</td>
                            <td class="cell-title">{{ $routine->class?->name }}</td>
                            <td>{{ $routine->section?->name }}</td>
                            <td class="cell-sub">{{ $routine->effective_from?->format('d M Y') ?? '-' }}</td>
                            <td class="num">{{ $routine->entries_count }}</td>
                            <td class="cell-sub">{{ $routine->updated_at?->format('d M Y, H:i') }}</td>
                            <td class="actions">
                                <a class="btn btn-secondary btn-sm" href="{{ route('routines.show', $routine) }}">View</a>
                                @can('routines.edit')
                                    <a class="btn btn-secondary btn-sm" href="{{ route('routines.edit', $routine) }}">Edit</a>
                                @endcan
                                @can('routines.copy')
                                    <a class="btn btn-secondary btn-sm" href="{{ route('routines.copy', $routine) }}">Copy</a>
                                @endcan
                                @can('routines.print')
                                    <a class="btn btn-secondary btn-sm" href="{{ route('routines.print', $routine) }}" target="_blank" rel="noopener">Print</a>
                                    <a class="btn btn-secondary btn-sm" href="{{ route('routines.pdf', $routine) }}" target="_blank" rel="noopener">PDF</a>
                                @endcan
                                @can('routines.delete')
                                    <form method="POST" action="{{ route('routines.destroy', $routine) }}" onsubmit="return confirm('Delete this routine and every timetable cell in it?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-icon" title="Delete routine"><x-icon name="trash" :size="15" /></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="table-empty">No class routines match these filters. Create one to start the timetable.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $routines->links() }}
    </div>
@endsection
