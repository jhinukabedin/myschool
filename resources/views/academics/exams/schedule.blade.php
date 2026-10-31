@extends('layouts.app')

@section('title', 'Exam Schedule')

@section('content')
    <x-page-header eyebrow="Academics" title="Exam Schedule" :subtitle="$session ? 'Scheduled papers for the '.$session->name.' session.' : 'No academic session is configured.'">
        <x-slot:actions>
            <x-button :href="route('exams.index')" variant="secondary" icon="arrow-left">Examinations</x-button>
            <button type="button" class="btn btn-secondary" onclick="window.print()"><x-icon name="printer" :size="15" /> Print</button>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <div class="card-header">
            <div><h3 class="card-title">Scheduled Papers</h3><p class="card-sub">{{ $subjects->count() }} paper(s) with a date</p></div>
            <form method="GET" action="{{ route('exams.schedule') }}" class="filter-bar" style="margin:0">
                <div class="field">
                    <select class="select" name="class_id">
                        <option value="">All classes</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" @selected(request('class_id') == $class->id)>{{ $class->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-button type="submit" variant="secondary" icon="filter">Filter</x-button>
            </form>
        </div>

        @forelse ($subjects->groupBy(fn ($subject) => $subject->exam_date?->toDateString()) as $date => $papers)
            <div class="form-section" style="padding-bottom:0">
                <div class="form-section-title"><x-icon name="calendar" :size="14" /> {{ \Illuminate\Support\Carbon::parse($date)->format('l, d M Y') }}</div>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Examination</th><th>Class</th><th>Subject</th><th>Start</th><th class="num">Duration</th><th class="num">Full marks</th><th class="num">Pass marks</th></tr></thead>
                    <tbody>
                        @foreach ($papers->sortBy('start_time') as $paper)
                            <tr>
                                <td><div class="cell-title">{{ $paper->examination?->name }}</div><div class="cell-sub">{{ ucfirst($paper->examination?->status ?? 'draft') }}</div></td>
                                <td>{{ $paper->class?->name }}</td>
                                <td>{{ $paper->subject?->name }}</td>
                                <td>{{ $paper->start_time ? Str::substr($paper->start_time, 0, 5) : 'TBA' }}</td>
                                <td class="num">{{ $paper->duration_minutes ? $paper->duration_minutes.' min' : '—' }}</td>
                                <td class="num">{{ number_format((float) $paper->full_marks, 2) }}</td>
                                <td class="num">{{ number_format((float) $paper->pass_marks, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <x-empty icon="calendar" title="No scheduled papers" message="Add exam subjects with dates to build the schedule." />
        @endforelse
    </div>
@endsection
