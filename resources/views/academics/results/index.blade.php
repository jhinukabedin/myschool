@extends('layouts.app')

@section('title', 'Class Result')

@php
    $passed = $results->where('result', 'Pass')->count();
    $failed = $results->where('result', 'Fail')->count();
    $averageGpa = $results->isNotEmpty() ? round($results->avg('gpa'), 2) : 0;
    $top = $results->first();
@endphp

@section('content')
    <x-page-header eyebrow="Academics" title="Class Result" :subtitle="$session ? 'Merit list and result sheets for the '.$session->name.' session.' : 'No academic session is configured.'">
        <x-slot:actions>
            <x-button :href="route('exams.marks-entry')" variant="secondary" icon="pencil">Marks entry</x-button>
            @if ($exam && $class)
                <x-button :href="route('exams.class-result.export', request()->only(['exam_id', 'class_id', 'section_id']))" variant="secondary" icon="download">Export CSV</x-button>
                <button type="button" class="btn btn-secondary" onclick="window.print()"><x-icon name="printer" :size="15" /> Print list</button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <div class="card-header">
            <div><h3 class="card-title">Result Filters</h3><p class="card-sub">Choose an examination and class to build the merit list</p></div>
            <form method="GET" action="{{ route('exams.class-result') }}" class="filter-bar" style="margin:0;flex-wrap:wrap">
                <div class="field" style="min-width:220px">
                    <label class="field-label" for="exam_id">Examination</label>
                    <select class="select" id="exam_id" name="exam_id">
                        @forelse ($exams as $examOption)
                            <option value="{{ $examOption->id }}" @selected($exam?->id === $examOption->id)>{{ $examOption->name }}</option>
                        @empty
                            <option value="">No examinations</option>
                        @endforelse
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="class_id">Class</label>
                    <select class="select" id="class_id" name="class_id">
                        <option value="">Select class</option>
                        @foreach ($classes as $classOption)
                            <option value="{{ $classOption->id }}" @selected($class?->id === $classOption->id)>{{ $classOption->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="section_id">Section</label>
                    <select class="select" id="section_id" name="section_id">
                        <option value="">All sections</option>
                        @foreach ($sections as $sectionOption)
                            <option value="{{ $sectionOption->id }}" @selected($section?->id === $sectionOption->id)>{{ $sectionOption->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-button type="submit" variant="secondary" icon="filter">Build results</x-button>
            </form>
        </div>
    </div>

    @if (! $exam || ! $class)
        <div class="card"><x-empty icon="file-bar-chart" title="Select an examination and class" message="The merit list appears after choosing both filters." /></div>
    @else
        <div class="stat-grid" style="grid-template-columns:repeat(4,minmax(0,1fr))">
            <x-stat-card label="Students" :value="number_format($results->count())" icon="users" tone="student" meta="In this class" />
            <x-stat-card label="Passed" :value="number_format($passed)" icon="check-circle" tone="collection" meta="All subjects passed" />
            <x-stat-card label="Failed" :value="number_format($failed)" icon="alert-circle" tone="pending" meta="At least one subject" />
            <x-stat-card label="Average GPA" :value="number_format($averageGpa, 2)" icon="graduation-cap" tone="expense" :meta="$top ? 'Top: '.$top['student']->full_name : 'No results'" />
        </div>

        <div class="card">
            <div class="card-header">
                <div><h3 class="card-title">{{ $exam->name }}</h3><p class="card-sub">{{ $class->name }}{{ $section ? ' · Section '.$section->name : '' }} · Ranked by GPA, then total marks</p></div>
                <x-badge :color="$exam->status === 'published' ? 'success' : 'warning'">{{ ucfirst($exam->status) }}</x-badge>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th class="num">Rank</th><th>Student</th><th>Section</th><th class="num">Total</th><th class="num">%</th><th class="num">GPA</th><th>Grade</th><th>Result</th><th class="actions">Result sheet</th></tr></thead>
                    <tbody>
                        @forelse ($results as $result)
                            <tr>
                                <td class="num"><span class="badge badge-violet">{{ $result['rank'] }}</span></td>
                                <td>
                                    <div class="cell-main">
                                        <span class="table-avatar">{{ Str::substr($result['student']->full_name, 0, 1) }}</span>
                                        <div>
                                            <div class="cell-title">{{ $result['student']->full_name }}</div>
                                            <div class="cell-sub">{{ $result['student']->admission_no }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $result['enrollment']?->section?->name ?? '—' }}</td>
                                <td class="num">{{ number_format($result['total'], 2) }} / {{ number_format($result['full_total'], 2) }}</td>
                                <td class="num">{{ number_format($result['percentage'], 2) }}%</td>
                                <td class="num">{{ number_format($result['gpa'], 2) }}</td>
                                <td>{{ $result['grade'] }}</td>
                                <td><x-badge :color="$result['result'] === 'Pass' ? 'success' : 'danger'">{{ $result['result'] }}</x-badge></td>
                                <td class="actions">
                                    <a href="{{ route('exams.result-sheet', [$exam, $result['student']]) }}" class="btn btn-secondary btn-sm"><x-icon name="file-text" :size="13" /> Open</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="table-empty">No students or marks were found for this class and examination.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
