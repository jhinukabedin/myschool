@extends('layouts.app')

@section('title', 'Marks Entry')

@section('content')
    <x-page-header eyebrow="Academics" title="Marks Entry" :subtitle="$session ? 'Enter or update subject marks for the '.$session->name.' session.' : 'No academic session is configured.'">
        <x-slot:actions>
            @if ($examSubject)
                @can('results.view')
                    <x-button :href="route('exams.class-result', ['exam_id' => $examSubject->examination_id, 'class_id' => $examSubject->class_id])" variant="secondary" icon="file-bar-chart">Class result</x-button>
                @endcan
            @endif
            <x-button :href="route('exams.index')" variant="ghost" icon="arrow-left">Examinations</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <div class="card-header">
            <div><h3 class="card-title">Choose Exam Paper</h3><p class="card-sub">Select the examination, subject and optional section</p></div>
            <form method="GET" action="{{ route('exams.marks-entry') }}" class="filter-bar" style="margin:0;flex-wrap:wrap">
                <div class="field">
                    <label class="field-label" for="exam_id">Examination</label>
                    <select class="select" id="exam_id" name="exam_id">
                        @foreach ($exams as $examOption)
                            <option value="{{ $examOption->id }}" @selected($exam?->id === $examOption->id)>{{ $examOption->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="min-width:240px">
                    <label class="field-label" for="examination_subject_id">Paper</label>
                    <select class="select" id="examination_subject_id" name="examination_subject_id">
                        <option value="">Select a paper</option>
                        @foreach ($examSubjects as $paper)
                            <option value="{{ $paper->id }}" @selected($examSubject?->id === $paper->id)>{{ $paper->class?->name }} · {{ $paper->subject?->name }}</option>
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
                <x-button type="submit" variant="secondary" icon="filter">Load students</x-button>
            </form>
        </div>
    </div>

    @if (! $exam)
        <div class="card"><x-empty icon="graduation-cap" title="No examination selected" message="Create an examination and add subjects before entering marks." /></div>
    @elseif ($examSubjects->isEmpty())
        <div class="card"><x-empty icon="book-open" title="No subjects in this examination" message="Add exam subjects and a schedule from the examination page first.">
            @can('exams.manage')
                <x-button :href="route('exams.show', $exam)" variant="primary" icon="plus">Add exam subjects</x-button>
            @endcan
        </x-empty></div>
    @elseif (! $examSubject)
        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Select a paper</h3><p class="card-sub">Pick a paper above, or use a shortcut below</p></div></div>
            <div class="card-body">
                <div class="quick-grid">
                    @foreach ($examSubjects as $paper)
                        <a class="quick-item" href="{{ route('exams.marks-entry', ['exam_id' => $exam->id, 'examination_subject_id' => $paper->id]) }}">
                            <span class="quick-icon violet"><x-icon name="book-open" :size="17" /></span>
                            <span class="quick-body">
                                <span class="quick-title">{{ $paper->subject?->name }}</span>
                                <span class="quick-sub">{{ $paper->class?->name }} · Full {{ number_format((float) $paper->full_marks, 0) }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <form method="POST" action="{{ route('exams.marks-store') }}">
            @csrf
            <input type="hidden" name="examination_subject_id" value="{{ $examSubject->id }}">
            @if ($section)<input type="hidden" name="section_id" value="{{ $section->id }}">@endif

            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ $examSubject->subject?->name }}</h3>
                        <p class="card-sub">{{ $examSubject->class?->name }}{{ $section ? ' · Section '.$section->name : ' · All sections' }} · {{ $exam->name }} · Full marks {{ number_format((float) $examSubject->full_marks, 2) }} · Pass {{ number_format((float) $examSubject->pass_marks, 2) }}</p>
                    </div>
                    <div style="display:flex;gap:6px;align-items:center">
                        <x-badge :color="$exam->status === 'published' ? 'success' : ($exam->status === 'completed' ? 'info' : 'neutral')">Exam {{ ucfirst($exam->status) }}</x-badge>
                        <x-badge color="violet">{{ $students->count() }} student(s)</x-badge>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Roll</th>
                                <th>Student</th>
                                @if ($components->isNotEmpty())
                                    @foreach ($components as $component)
                                        <th style="min-width:110px">{{ $component['name'] }}<div class="cell-sub">Full {{ number_format((float) $component['full_marks'], 0) }}</div></th>
                                    @endforeach
                                @else
                                    <th style="width:170px">Marks obtained</th>
                                @endif
                                <th>Absent</th>
                                <th>Special status</th>
                                <th>Current grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($students as $enrollment)
                                @php($mark = $marks->get($enrollment->student_id))
                                <tr>
                                    <td class="muted">{{ $enrollment->roll_no }}</td>
                                    <td>
                                        <div class="cell-main">
                                            <span class="table-avatar">{{ Str::substr($enrollment->student?->full_name ?? '?', 0, 1) }}</span>
                                            <div>
                                                <div class="cell-title">{{ $enrollment->student?->full_name }}</div>
                                                <div class="cell-sub">{{ $enrollment->student?->admission_no }} · Section {{ $enrollment->section?->name }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    @if ($components->isNotEmpty())
                                        @foreach ($components as $componentIndex => $component)
                                            <td>
                                                <input class="input" type="number" step="0.01" min="0" max="{{ (float) $component['full_marks'] }}" name="components[{{ $enrollment->student_id }}][{{ $componentIndex }}]" value="{{ old('components.'.$enrollment->student_id.'.'.$componentIndex, $mark && ! $mark->is_absent ? ($mark->component_marks[$componentIndex] ?? null) : null) }}" placeholder="0 - {{ number_format((float) $component['full_marks'], 0) }}">
                                                @error('components.'.$enrollment->student_id.'.'.$componentIndex)
                                                    <span class="field-error">{{ $message }}</span>
                                                @enderror
                                            </td>
                                        @endforeach
                                    @else
                                        <td>
                                            <input class="input" type="number" step="0.01" min="0" max="{{ (float) $examSubject->full_marks }}" name="marks[{{ $enrollment->student_id }}]" value="{{ old('marks.'.$enrollment->student_id, $mark && ! $mark->is_absent ? $mark->marks_obtained : null) }}" placeholder="0 - {{ number_format((float) $examSubject->full_marks, 0) }}">
                                            @error('marks.'.$enrollment->student_id)
                                                <span class="field-error">{{ $message }}</span>
                                            @enderror
                                        </td>
                                    @endif
                                    <td>
                                        <label class="checkbox">
                                            <input type="checkbox" name="absent[{{ $enrollment->student_id }}]" value="1" @checked(old('absent.'.$enrollment->student_id, $mark?->is_absent))>
                                            <span>Absent</span>
                                        </label>
                                    </td>
                                    <td>
                                        <select class="select" name="special_status[{{ $enrollment->student_id }}]" aria-label="Special status">
                                            <option value="">None</option>
                                            <option value="exempt" @selected(old('special_status.'.$enrollment->student_id, $mark?->special_status) === 'exempt')>Exempt</option>
                                            <option value="withheld" @selected(old('special_status.'.$enrollment->student_id, $mark?->special_status) === 'withheld')>Withheld</option>
                                            <option value="incomplete" @selected(old('special_status.'.$enrollment->student_id, $mark?->special_status) === 'incomplete')>Incomplete</option>
                                            <option value="disqualified" @selected(old('special_status.'.$enrollment->student_id, $mark?->special_status) === 'disqualified')>Disqualified</option>
                                        </select>
                                    </td>
                                    <td>
                                        @if ($mark)
                                            <x-badge :color="$mark->is_absent || $mark->grade === 'F' || in_array($mark->special_status, ['withheld', 'incomplete', 'disqualified'], true) ? 'danger' : 'success'">
                                                {{ $mark->special_status ? strtoupper($mark->special_status) : ($mark->is_absent ? 'Absent' : ($mark->grade ?? '—')) }}
                                            </x-badge>
                                            @if (! $mark->is_absent && $mark->grade_point !== null)<span class="cell-sub">GP {{ number_format((float) $mark->grade_point, 2) }}</span>@endif
                                            <div><x-badge :color="$mark->status === 'approved' ? 'success' : ($mark->status === 'submitted' ? 'info' : 'neutral')">{{ ucfirst($mark->status) }}</x-badge></div>
                                        @else
                                            <span class="muted">Not entered</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ 5 + max(1, $components->count()) }}" class="table-empty">No active students match this paper.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($students->isNotEmpty())
                    @php($needsReason = $exam->status === 'published' || $marks->contains(fn ($mark) => in_array($mark->status, ['submitted', 'approved'], true)))
                    <div class="form-section" style="display:grid;gap:12px">
                        @if ($needsReason)
                            <div class="field">
                                <label class="field-label" for="correction_reason">Correction reason <span class="required-mark">*</span></label>
                                <textarea id="correction_reason" class="input" name="correction_reason" rows="2" maxlength="500" required placeholder="Explain why finalized or published marks are being corrected. This is stored in the audit log.">{{ old('correction_reason') }}</textarea>
                                @error('correction_reason')<span class="field-error">{{ $message }}</span>@enderror
                            </div>
                        @endif
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                            <span class="muted">Enter marks or select a special status. Blank rows are skipped. Unchanged finalized rows are left untouched.</span>
                            @can('marks.enter')
                                <x-button type="submit" variant="primary" icon="save">Save marks</x-button>
                            @endcan
                        </div>
                    </div>
                @endif
            </div>
        </form>

        <div class="card">
            <div class="card-header">
                <div><h3 class="card-title">Marks Workflow</h3><p class="card-sub">Draft → Submitted → Approved. Changes after approval require the correction permission.</p></div>
            </div>
            <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                @if ($exam->status === 'published')
                    <div class="warning-box" style="flex:1 1 100%">This examination is published. Marks are locked unless your account has the "Correct finalized marks" permission.</div>
                @endif
                @can('marks.submit')
                    <form method="POST" action="{{ route('exams.marks-submit') }}">
                        @csrf
                        <input type="hidden" name="examination_subject_id" value="{{ $examSubject->id }}">
                        @if ($section)<input type="hidden" name="section_id" value="{{ $section->id }}">@endif
                        <x-button type="submit" variant="secondary" icon="send">Submit draft marks</x-button>
                    </form>
                @endcan
                @can('marks.approve')
                    <form method="POST" action="{{ route('exams.marks-approve') }}">
                        @csrf
                        <input type="hidden" name="examination_subject_id" value="{{ $examSubject->id }}">
                        @if ($section)<input type="hidden" name="section_id" value="{{ $section->id }}">@endif
                        <x-button type="submit" variant="secondary" icon="check-circle">Approve submitted marks</x-button>
                    </form>
                @endcan
            </div>
        </div>
    @endif
@endsection
