@extends('layouts.app')

@section('title', $exam->name)

@section('content')
    <x-page-header eyebrow="Examination" :title="$exam->name" :subtitle="($exam->academicSession?->name ?? 'No session').' · '.$exam->start_date?->format('d M Y').' – '.$exam->end_date?->format('d M Y')">
        <x-slot:actions>
            @can('exams.manage')
                <x-button :href="route('exams.edit', $exam)" variant="secondary" icon="edit">Edit</x-button>
                <x-button :href="route('grading-schemes.index')" variant="secondary" icon="sliders">Grading schemes</x-button>
                @if ($exam->status !== 'published' && $marksCount === 0)
                    <form method="POST" action="{{ route('exams.destroy', $exam) }}" onsubmit="return confirm('Delete this examination and its subject schedule? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="danger" icon="trash">Delete</x-button>
                    </form>
                @endif
            @endcan
            @can('marks.enter')
                <x-button :href="route('exams.marks-entry', ['exam_id' => $exam->id])" variant="secondary" icon="pencil">Marks entry</x-button>
            @endcan
            @can('results.view')
                <x-button :href="route('exams.class-result', ['exam_id' => $exam->id])" variant="primary" icon="file-bar-chart">Class results</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="stat-grid" style="grid-template-columns:repeat(4,minmax(0,1fr))">
        <x-stat-card label="Subjects" :value="number_format($exam->subjects->count())" icon="book-open" tone="student" meta="Across classes" />
        <x-stat-card label="Classes" :value="number_format($exam->subjects->pluck('class_id')->unique()->count())" icon="school" tone="collection" meta="Covered" />
        <x-stat-card label="Marks entered" :value="number_format($marksCount)" icon="pencil" tone="expense" meta="Student-subject rows" />
        <x-stat-card label="Status" :value="ucfirst($exam->status)" icon="shield" :tone="$exam->status === 'published' ? 'collection' : 'pending'" :meta="ucfirst(str_replace('_', ' ', $exam->exam_type))" />
    </div>

    @if ($exam->status === 'published')
        <div class="hero-note" style="margin-bottom:16px">
            Results were published {{ $exam->result_published_at?->format('d M Y, h:i A') }} and locked. {{ $snapshotCount }} student snapshot(s) are visible in the portals using the stored grading rules.
        </div>
    @elseif ($marksCount === 0)
        <div class="warning-box" style="margin-bottom:16px">No marks have been entered. Publishing requires approved marks for this examination.</div>
    @elseif ($pendingMarks > 0)
        <div class="warning-box" style="margin-bottom:16px">{{ $pendingMarks }} mark row(s) are still draft or submitted. Approve every mark before publishing results.</div>
    @endif

    <div class="grid-main-side">
        <div class="card">
            <div class="card-header">
                <div><h3 class="card-title">Exam Subjects & Schedule</h3><p class="card-sub">Remove is blocked once marks exist</p></div>
                @if ($exam->status !== 'published')
                    @can('exams.manage')
                        <form method="POST" action="{{ route('exams.publish', $exam) }}">
                            @csrf
                            <x-button type="submit" variant="success" size="sm" icon="check-circle">Publish &amp; lock results</x-button>
                        </form>
                    @endcan
                @else
                    <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                        <x-badge color="success">Published &amp; locked</x-badge>
                        <x-badge color="neutral">{{ $snapshotCount }} snapshots</x-badge>
                    </div>
                @endif
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Class</th><th>Subject</th><th class="num">Full</th><th class="num">Pass</th><th>Date</th><th>Time</th><th class="num">Marks</th><th class="actions"></th></tr></thead>
                    <tbody>
                        @forelse ($exam->subjects->sortBy([['class_id', 'asc'], ['exam_date', 'asc']]) as $examSubject)
                            <tr>
                                <td class="cell-title">{{ $examSubject->class?->name }}</td>
                                <td>
                                    {{ $examSubject->subject?->name }}
                                    @if (! empty($examSubject->components))
                                        <div class="cell-sub">
                                            {{ collect($examSubject->components)->map(fn ($component) => $component['name'].' '.number_format((float) $component['full_marks'], 0))->implode(' + ') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="num">{{ number_format((float) $examSubject->full_marks, 2) }}</td>
                                <td class="num">{{ number_format((float) $examSubject->pass_marks, 2) }}</td>
                                <td>{{ $examSubject->exam_date?->format('d M Y') ?? '—' }}</td>
                                <td>{{ $examSubject->start_time ? Str::substr($examSubject->start_time, 0, 5) : '—' }}{{ $examSubject->duration_minutes ? ' · '.$examSubject->duration_minutes.' min' : '' }}</td>
                                <td class="num">{{ $examSubject->marks()->count() }}</td>
                                <td class="actions">
                                    @can('exams.manage')
                                        <form method="POST" action="{{ route('exams.subjects.destroy', [$exam, $examSubject]) }}" onsubmit="return confirm('Remove this subject from the examination? Marks must not exist.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-icon" title="Remove subject"><x-icon name="trash" :size="15" /></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="table-empty">No subjects have been added to this examination.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="stack">
            @can('exams.manage')
                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">Add Subject</h3><p class="card-sub">Maps the subject to a class and schedule</p></div></div>
                    <form method="POST" action="{{ route('exams.subjects.store', $exam) }}">
                        @csrf
                        <div class="form-section">
                            <div class="stack">
                                <x-select name="class_id" label="Class" :options="$classes->pluck('name', 'id')" required />
                                <x-select name="subject_id" label="Subject" :options="$subjects->pluck('name', 'id')" required />
                                <div class="form-grid">
                                    <x-input name="full_marks" label="Full marks" type="number" step="0.01" min="1" max="999" value="100" required />
                                    <x-input name="pass_marks" label="Pass marks" type="number" step="0.01" min="0" max="999" value="33" required />
                                    <x-input name="exam_date" label="Exam date" type="date" />
                                    <x-input name="start_time" label="Start time" type="time" />
                                    <x-input name="duration_minutes" label="Duration (minutes)" type="number" min="1" max="600" placeholder="120" />
                                </div>

                                <div x-data="{ rows: [] }" style="display:grid;gap:8px">
                                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
                                        <div>
                                            <div class="field-label">Assessment components <span class="muted">(optional)</span></div>
                                            <p class="muted" style="margin:2px 0 0;font-size:12px">Use theory, practical, MCQ or other components. Component full marks must add up to the paper’s full marks.</p>
                                        </div>
                                        <button type="button" class="btn btn-secondary btn-sm" @click="rows.push({name: '', full_marks: '', pass_marks: ''})">Add component</button>
                                    </div>
                                    <template x-for="(row, index) in rows" :key="index">
                                        <div class="form-grid" style="grid-template-columns:2fr 1fr 1fr auto;align-items:end">
                                            <input class="input" type="text" :name="`components[${index}][name]`" x-model="row.name" maxlength="80" placeholder="Component name">
                                            <input class="input" type="number" step="0.01" min="0.01" max="999" :name="`components[${index}][full_marks]`" x-model="row.full_marks" placeholder="Full">
                                            <input class="input" type="number" step="0.01" min="0" max="999" :name="`components[${index}][pass_marks]`" x-model="row.pass_marks" placeholder="Pass">
                                            <button type="button" class="btn-icon" title="Remove component" @click="rows.splice(index, 1)"><x-icon name="trash" :size="15" /></button>
                                        </div>
                                    </template>
                                    @error('components')<span class="field-error">{{ $message }}</span>@enderror
                                </div>

                                <x-button type="submit" variant="primary" icon="plus" class="btn-block">Add subject</x-button>
                            </div>
                        </div>
                    </form>
                </div>
            @endcan

            <div class="card">
                <div class="card-header"><div><h3 class="card-title">Examination Info</h3></div></div>
                <div class="card-body">
                    <div class="detail-list">
                        <div class="detail-row"><span class="detail-label">Type</span><span class="detail-value">{{ ucwords(str_replace('_', ' ', $exam->exam_type)) }}</span></div>
                        <div class="detail-row"><span class="detail-label">Session</span><span class="detail-value">{{ $exam->academicSession?->name }}</span></div>
                        <div class="detail-row"><span class="detail-label">Starts</span><span class="detail-value">{{ $exam->start_date?->format('d M Y') }}</span></div>
                        <div class="detail-row"><span class="detail-label">Ends</span><span class="detail-value">{{ $exam->end_date?->format('d M Y') }}</span></div>
                        <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value">{{ ucfirst($exam->status) }}</span></div>
                        <div class="detail-row"><span class="detail-label">Grading scheme</span><span class="detail-value">{{ $exam->gradingScheme?->name ?? 'Institution default' }}</span></div>
                        <div class="detail-row"><span class="detail-label">Published</span><span class="detail-value">{{ $exam->result_published_at?->format('d M Y, h:i A') ?? 'Not published' }}</span></div>
                        <div class="detail-row"><span class="detail-label">Result snapshots</span><span class="detail-value">{{ $snapshotCount }}</span></div>
                        @if ($exam->result_note)
                            <div class="detail-row"><span class="detail-label">Note</span><span class="detail-value">{{ $exam->result_note }}</span></div>
                        @endif
                    </div>

                    @if ($exam->status === 'published')
                        @can('marks.correct')
                            <details style="margin-top:14px">
                                <summary class="muted" style="cursor:pointer;font-size:12px">Unpublish and reopen for corrections</summary>
                                <form method="POST" action="{{ route('exams.unpublish', $exam) }}" style="display:grid;gap:8px;margin-top:10px" onsubmit="return confirm('Hide these results from portals and reopen marks for correction?')">
                                    @csrf
                                    <textarea class="input" name="reason" rows="2" required minlength="5" maxlength="500" placeholder="Reason for reopening (audited)"></textarea>
                                    <button class="btn btn-danger btn-sm" type="submit">Unpublish results</button>
                                </form>
                            </details>
                        @endcan
                    @endif
                </div>
            </div>

            @if ($exam->subjects->isNotEmpty())
                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">Class Subject Map</h3><p class="card-sub">Available mappings for this session</p></div></div>
                    <div class="card-body">
                        @forelse ($classSubjects->groupBy('class_id') as $classId => $mappings)
                            <div class="detail-row">
                                <span class="detail-label">{{ $mappings->first()->class?->name }}</span>
                                <span class="detail-value">{{ $mappings->pluck('subject.name')->filter()->join(', ') }}</span>
                            </div>
                        @empty
                            <p class="muted" style="font-size:12px;margin:0">No class-subject mappings were found for the classes in this exam.</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
