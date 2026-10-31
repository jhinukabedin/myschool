@extends('layouts.app')

@section('title', $exam->exists ? 'Edit Examination' : 'New Examination')

@section('content')
    <x-page-header eyebrow="Academics" :title="$exam->exists ? 'Edit Examination' : 'New Examination'" subtitle="Define the examination period. Subjects, schedules and marks are added after saving.">
        <x-slot:actions>
            <x-button :href="route('exams.index')" variant="secondary" icon="arrow-left">Examinations</x-button>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ $exam->exists ? route('exams.update', $exam) : route('exams.store') }}">
        @csrf
        @if ($exam->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Examination Details</h3><p class="card-sub">Names are unique within an academic session</p></div></div>
            <div class="form-section">
                <div class="form-grid">
                    <div class="span-2"><x-input name="name" label="Examination name" :value="$exam->name" required placeholder="e.g. First Term Examination 2026" /></div>
                    <x-select name="academic_session_id" label="Academic session" :options="$sessions->pluck('name', 'id')" :selected="$exam->academic_session_id" required />
                    <x-select name="exam_type" label="Examination type" :options="['term' => 'Term examination', 'mid_term' => 'Mid-term', 'final' => 'Final', 'annual' => 'Annual', 'ssc' => 'SSC / equivalent', 'hsc' => 'HSC / equivalent', 'unit_test' => 'Unit test', 'other' => 'Other']" :selected="$exam->exam_type" required />
                    <x-select name="grading_scheme_id" label="Grading scheme" :options="$gradingSchemes->pluck('name', 'id')->prepend('Institution default', '')" :selected="$exam->grading_scheme_id" help="Published results store the chosen boundaries. Configure schemes under Grading schemes." />
                    <x-input name="start_date" label="Start date" type="date" :value="$exam->start_date?->toDateString()" required />
                    <x-input name="end_date" label="End date" type="date" :value="$exam->end_date?->toDateString()" required help="Must be on or after the start date." />
                    <x-select name="status" label="Status" :options="['draft' => 'Draft', 'published' => 'Published', 'completed' => 'Completed']" :selected="$exam->status" required help="Publishing and reopening are done from the examination page, after every mark is approved." />
                </div>
            </div>
            <div class="form-section" style="display:flex;justify-content:flex-end;gap:8px">
                <x-button :href="route('exams.index')" variant="ghost">Cancel</x-button>
                <x-button type="submit" variant="primary" icon="save">{{ $exam->exists ? 'Save examination' : 'Create examination' }}</x-button>
            </div>
        </div>
    </form>
@endsection
