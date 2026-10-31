@extends('layouts.app')

@section('title', $routine->exists ? 'Edit Class Routine' : 'Create Class Routine')

@section('content')
    <x-page-header eyebrow="Academic management" :title="$routine->exists ? 'Edit Class Routine' : 'Create Class Routine'"
        subtitle="A routine is unique for an academic year, class and section. The timetable grid opens after the routine is saved.">
        <x-slot:actions>
            <x-button :href="$routine->exists ? route('routines.show', $routine) : route('routines.index')" variant="secondary" icon="arrow-left">Back</x-button>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ $routine->exists ? route('routines.update', $routine) : route('routines.store') }}">
        @csrf
        @if ($routine->exists)
            @method('PUT')
        @endif

        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Routine details</h3>
                    <p class="card-sub">The academic year, class and section identify the official timetable.</p>
                </div>
            </div>
            <div class="form-section">
                <div class="form-grid">
                    <x-select name="academic_session_id" label="Academic year" :options="$sessions->pluck('name', 'id')" :selected="$routine->academic_session_id" required />
                    <x-select name="class_id" label="Class" :options="$classes->pluck('name', 'id')" :selected="$routine->class_id" required />
                    <x-select name="section_id" label="Section" :options="$sections->pluck('name', 'id')" :selected="$routine->section_id" required />
                    <x-input name="effective_from" label="Effective from" type="date" :value="$routine->effective_from?->format('Y-m-d')" required help="Shown on the printed routine." />
                    <x-input name="effective_to" label="Effective to (optional)" type="date" :value="$routine->effective_to?->format('Y-m-d')" />
                    <div class="span-2">
                        <label class="field">
                            <span class="field-label">Notes</span>
                            <textarea class="textarea" name="notes" rows="3" maxlength="2000" placeholder="Optional note for staff">{{ old('notes', $routine->notes) }}</textarea>
                            <span class="field-hint">Internal note, not printed unless you print the routine notes separately.</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="form-section" style="display:flex;justify-content:flex-end;gap:8px">
                <x-button :href="$routine->exists ? route('routines.show', $routine) : route('routines.index')" variant="ghost">Cancel</x-button>
                <x-button type="submit" variant="primary" icon="save">{{ $routine->exists ? 'Save details' : 'Create and open timetable' }}</x-button>
            </div>
        </div>
    </form>
@endsection
