@extends('layouts.app')

@section('title', 'Examinations')

@section('content')
    <x-page-header eyebrow="Academics" title="Examinations" :subtitle="$session ? 'Examinations scheduled for the '.$session->name.' session.' : 'No academic session is configured.'">
        <x-slot:actions>
            <x-button :href="route('grading-schemes.index')" variant="secondary" icon="sliders">Grading schemes</x-button>
            <x-button :href="route('exams.schedule')" variant="secondary" icon="calendar">Exam schedule</x-button>
            @can('exams.manage')
                <x-button :href="route('exams.create')" variant="primary" icon="plus">New examination</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <div class="card-header">
            <div><h3 class="card-title">All Examinations</h3><p class="card-sub">{{ $exams->total() }} examination(s)</p></div>
            <span class="badge badge-violet">{{ $session?->name ?? 'All sessions' }}</span>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Examination</th><th>Session</th><th>Type</th><th>Period</th><th class="num">Subjects</th><th>Status</th><th class="actions">Actions</th></tr></thead>
                <tbody>
                    @forelse ($exams as $exam)
                        <tr>
                            <td>
                                <div class="cell-title"><a href="{{ route('exams.show', $exam) }}">{{ $exam->name }}</a></div>
                                <div class="cell-sub">{{ $exam->academicSession?->name }}</div>
                            </td>
                            <td>{{ $exam->academicSession?->name }}</td>
                            <td>{{ ucwords(str_replace('_', ' ', $exam->exam_type)) }}</td>
                            <td class="muted">{{ $exam->start_date?->format('d M Y') }} – {{ $exam->end_date?->format('d M Y') }}</td>
                            <td class="num">{{ $exam->subjects_count }}</td>
                            <td><x-badge :color="$exam->status_color">{{ ucfirst($exam->status) }}</x-badge></td>
                            <td class="actions">
                                <a href="{{ route('exams.show', $exam) }}" class="btn-icon" title="Open"><x-icon name="eye" :size="15" /></a>
                                @can('exams.manage')
                                    <a href="{{ route('exams.edit', $exam) }}" class="btn-icon" title="Edit"><x-icon name="edit" :size="15" /></a>
                                @endcan
                                @can('marks.enter')
                                    <a href="{{ route('exams.marks-entry', ['exam_id' => $exam->id]) }}" class="btn-icon" title="Marks entry"><x-icon name="pencil" :size="15" /></a>
                                @endcan
                                @can('results.view')
                                    <a href="{{ route('exams.class-result', ['exam_id' => $exam->id]) }}" class="btn-icon" title="Class results"><x-icon name="file-bar-chart" :size="15" /></a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="table-empty">No examinations have been created for this session.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $exams->links() }}
    </div>
@endsection
