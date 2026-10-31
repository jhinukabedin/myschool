@extends('layouts.app')

@section('title', 'Copy Class Routine')

@section('content')
    <x-page-header eyebrow="Academic management" title="Copy Class Routine"
        subtitle="Copy every timetable cell into another academic year, class or section. Teacher, room and class conflicts are checked before anything is saved.">
        <x-slot:actions>
            <x-button :href="route('routines.show', $routine)" variant="secondary" icon="arrow-left">Back to routine</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid-side-main">
        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Source routine</h3><p class="card-sub">This timetable will be copied unchanged.</p></div></div>
            <div class="form-section">
                <div class="stack">
                    <div><div class="receipt-label">Academic year</div><div class="cell-title">{{ $routine->academicSession?->name }}</div></div>
                    <div><div class="receipt-label">Class</div><div class="cell-title">{{ $routine->class?->name }}</div></div>
                    <div><div class="receipt-label">Section</div><div class="cell-title">{{ $routine->section?->name }}</div></div>
                    <div><div class="receipt-label">Timetable cells</div><div class="cell-title">{{ $routine->entries()->count() }}</div></div>
                    <div><div class="receipt-label">Effective from</div><div class="cell-title">{{ $routine->effectiveDate()->format('d M Y') }}</div></div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('routines.copy.store', $routine) }}">
            @csrf
            <div class="card">
                <div class="card-header"><div><h3 class="card-title">Destination</h3><p class="card-sub">Choose a context that does not already have a routine.</p></div></div>
                <div class="form-section">
                    <div class="form-grid">
                        <x-select name="academic_session_id" label="Academic year" :options="$sessions->pluck('name', 'id')" :selected="$targetSessionId" required />
                        <x-select name="class_id" label="Class" :options="$classes->pluck('name', 'id')" required />
                        <x-select name="section_id" label="Section" :options="$sections->pluck('name', 'id')" required />
                        <x-input name="effective_from" label="Effective from" type="date" :value="$targetEffectiveFrom" required />
                        <x-input name="effective_to" label="Effective to (optional)" type="date" />
                        <div class="span-2">
                            <label class="field">
                                <span class="field-label">Notes</span>
                                <textarea class="textarea" name="notes" rows="3" maxlength="2000" placeholder="Optional note">{{ old('notes', $routine->notes) }}</textarea>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="form-section" style="display:flex;justify-content:flex-end;gap:8px">
                    <x-button :href="route('routines.show', $routine)" variant="ghost">Cancel</x-button>
                    <x-button type="submit" variant="primary" icon="copy">Copy routine</x-button>
                </div>
            </div>
        </form>
    </div>
@endsection
