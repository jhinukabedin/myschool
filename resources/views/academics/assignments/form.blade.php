@extends('layouts.app')

@section('title', $assignment->exists ? 'Edit Teacher Assignment' : 'New Teacher Assignment')

@section('content')
    <x-page-header eyebrow="Academics" :title="$assignment->exists ? 'Edit Teacher Assignment' : 'New Teacher Assignment'"
        subtitle="Assign a teacher to a class, section and subject. Historical assignments are deactivated, never deleted.">
        <x-slot:actions>
            <x-button :href="route('assignments.index')" variant="secondary" icon="arrow-left">Assignments</x-button>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ $assignment->exists ? route('assignments.update', $assignment) : route('assignments.store') }}">
        @csrf
        @if ($assignment->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Assignment Details</h3><p class="card-sub">Duplicate active assignments are rejected unless co-teaching is enabled.</p></div></div>
            <div class="form-section">
                <div class="form-grid">
                    <x-select name="academic_session_id" label="Academic session" :options="$sessions->pluck('name', 'id')" :selected="$assignment->academic_session_id" required />
                    <x-select name="class_id" label="Class" :options="$classes->pluck('name', 'id')" :selected="$assignment->class_id" required />
                    <x-select name="section_id" label="Section" placeholder="Whole class" :options="$sections->pluck('name', 'id')" :selected="$assignment->section_id" help="Leave blank to assign the whole class." />
                    @if ($assignment->exists)
                        <x-select name="subject_id" label="Subject" :options="$subjects->pluck('name', 'id')" :selected="$assignment->subject_id" required />
                    @else
                        <div class="span-2">
                            <div class="field">
                                <span class="field-label">Subjects</span>
                                <div style="display:flex;justify-content:flex-end;margin-bottom:8px">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="document.querySelectorAll('.assignment-subject').forEach(input => input.checked = true)">Select all</button>
                                </div>
                                <div class="form-grid" style="grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:8px">
                                    @foreach ($subjects as $subject)
                                        <label class="checkbox" style="padding:9px 10px;border:1px solid var(--line);border-radius:8px">
                                            <input class="assignment-subject" type="checkbox" name="subject_ids[]" value="{{ $subject->id }}" @checked(in_array($subject->id, old('subject_ids', [])))>
                                            <span>{{ $subject->name }} <small class="muted">{{ $subject->code }}</small></span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('subject_ids') <span class="field-error">{{ $message }}</span> @enderror
                                @error('subject_ids.*') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    @endif
                    <div class="span-2">
                        <x-select name="staff_id" label="Teacher" :options="$teachers->mapWithKeys(fn ($teacher) => [$teacher->id => $teacher->full_name.' ('.$teacher->employee_no.')'])" :selected="$assignment->staff_id" required />
                    </div>
                    <x-input name="starts_on" label="Starts on" type="date" :value="$assignment->starts_on?->format('Y-m-d')" />
                    <x-input name="ends_on" label="Ends on" type="date" :value="$assignment->ends_on?->format('Y-m-d')" help="Leave blank for an open-ended assignment." />
                    <div class="span-2">
                        <x-input name="notes" label="Notes" :value="$assignment->notes" placeholder="Optional internal note" />
                    </div>
                    <div class="span-2">
                        <input type="hidden" name="is_active" value="0">
                        <label class="checkbox">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $assignment->is_active ?? true))>
                            <span>Active assignment</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="form-section" style="display:flex;justify-content:flex-end;gap:8px">
                <x-button :href="route('assignments.index')" variant="ghost">Cancel</x-button>
                <x-button type="submit" variant="primary" icon="save">{{ $assignment->exists ? 'Save assignment' : 'Create assignment' }}</x-button>
            </div>
        </div>
    </form>
@endsection
