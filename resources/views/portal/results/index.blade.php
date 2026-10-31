@extends('layouts.portal')

@section('title', 'Results')
@section('banner-subtitle', 'প্রকাশিত পরীক্ষার ফলাফল ও গ্রেড')

@section('content')
    @if ($portalPrefix === 'parent' && isset($children) && $children->count() > 1)
        <div class="child-select" aria-label="Select child">
            @foreach ($children as $childOption)
                <a href="{{ route('parent.results.index', $childOption) }}" @class(['active' => $childOption->id === $student->id])>{{ $childOption->full_name }}</a>
            @endforeach
        </div>
    @endif

    <div class="portal-panel" style="margin-bottom:16px">
        <h2>{{ $portalPrefix === 'parent' ? $student->full_name.'\'s Results' : 'আমার ফলাফল' }}</h2>
        <div class="portal-panel-body">
            <div class="chips">
                @if ($enrollment)
                    <span class="chip">{{ $enrollment->class?->name }} · {{ $enrollment->section?->name ?? 'No section' }}</span>
                @endif
                <span class="chip">{{ $session?->name ?? 'No session' }}</span>
                <span class="chip">{{ $results->count() }} published result(s)</span>
            </div>
            <p class="small muted" style="margin:10px 0 0">Only examinations published by the school are shown. Grading uses the scheme stored at publication time.</p>
        </div>
    </div>

    @if ($results->isEmpty())
        <div class="portal-panel">
            <div class="empty">
                <strong>No published results are available.</strong><br>
                Results appear here after the examination controller publishes and locks them. Draft marks are never visible.
            </div>
        </div>
    @else
        <div class="portal-panels">
            @foreach ($results as $row)
                @php
                    $result = $row['result'];
                    $sheetUrl = $portalPrefix === 'parent'
                        ? route('parent.results.show', [$student, $row['exam']])
                        : route('student.results.show', $row['exam']);
                    $badge = in_array($result['result'], ['Pass'], true) ? 'badge-success' : ($result['result'] === 'Fail' ? 'badge-danger' : 'badge-warning');
                @endphp
                <div class="portal-panel">
                    <h2>{{ $row['exam']->name }}</h2>
                    <div class="portal-panel-body">
                        <p class="small muted" style="margin:0 0 12px">{{ $row['exam']->academicSession?->name }} · published {{ $row['published_at']?->format('d M Y') }}</p>
                        <div class="result-summary" style="grid-template-columns:repeat(2,minmax(0,1fr))">
                            <div><span>GPA</span><strong>{{ number_format($result['gpa'], 2) }}</strong></div>
                            <div><span>Grade</span><strong>{{ $result['grade'] }}</strong></div>
                            <div><span>Total</span><strong>{{ number_format($result['total'], 2) }} / {{ number_format($result['full_total'], 0) }}</strong></div>
                            <div><span>Result</span><strong><span class="badge {{ $badge }}">{{ $result['result'] }}</span></strong></div>
                        </div>
                        <div class="chips" style="margin-top:14px">
                            <span class="chip">{{ $row['exam']->exam_type }}</span>
                            @if ($row['exam']->gradingScheme)<span class="chip">{{ $row['exam']->gradingScheme->name }}</span>@endif
                            @if ($row['snapshot'])<span class="chip chip-success">Archived mark sheet</span>@endif
                        </div>
                        <p style="margin:14px 0 0"><a class="btn btn-purple btn-sm" href="{{ $sheetUrl }}">Open result sheet</a></p>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
