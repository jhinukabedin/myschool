@extends('layouts.app')

@section('title', $routine->exists ? __('Edit Class Routine') : __('Create Class Routine'))

@section('content')
    <x-page-header eyebrow="{{ __('Academic management') }}" :title="$routine->exists ? __('Edit Class Routine') : __('Create Class Routine')"
        subtitle="{{ __('A routine is unique for an academic year, class and section. The timetable grid opens after the routine is saved.') }}">
        <x-slot:actions>
            <x-button :href="$routine->exists ? route('routines.show', $routine) : route('routines.index')" variant="secondary" icon="arrow-left">{{ __('Back') }}</x-button>
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
                    <h3 class="card-title">{{ __('Routine details') }}</h3>
                    <p class="card-sub">{{ __('The academic year, class and section identify the official timetable.') }}</p>
                </div>
            </div>
            <div class="form-section">
                <div class="form-grid">
                    <x-select name="academic_session_id" :label="__('Academic year')" :options="$sessions->pluck('name', 'id')" :selected="$routine->academic_session_id" required />
                    <x-select name="class_id" :label="__('Class')" :options="$classes->pluck('name', 'id')" :selected="$routine->class_id" required />
                    <label class="field" for="section_id">
                        <span class="field-label">{{ __('Section') }} <span class="required-mark">*</span></span>
                        <select class="select" id="section_id" name="section_id" required>
                            <option value="">{{ __('Choose a class first') }}</option>
                            @foreach ($sections as $section)
                                <option value="{{ $section->id }}" data-class-ids="{{ $section->classes->pluck('id')->join(',') }}" @selected((string) old('section_id', $routine->section_id) === (string) $section->id)>{{ $section->name }}</option>
                            @endforeach
                        </select>
                        <span class="field-hint">{{ __('Only sections configured for the selected class are shown.') }}</span>
                        @error('section_id')<span class="field-error">{{ $message }}</span>@enderror
                    </label>
                    <x-input name="effective_from" :label="__('Effective from')" type="date" :value="$routine->effective_from?->format('Y-m-d')" required :help="__('Shown on the printed routine.')" />
                    <x-input name="effective_to" :label="__('Effective to (optional)')" type="date" :value="$routine->effective_to?->format('Y-m-d')" />
                    <div class="span-2">
                        <label class="field">
                            <span class="field-label">{{ __('Notes') }}</span>
                            <textarea class="textarea" name="notes" rows="3" maxlength="2000" placeholder="{{ __('Optional note for staff') }}">{{ old('notes', $routine->notes) }}</textarea>
                            <span class="field-hint">{{ __('Internal note, not printed unless you print the routine notes separately.') }}</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="form-section" style="display:flex;justify-content:flex-end;gap:8px">
                <x-button :href="$routine->exists ? route('routines.show', $routine) : route('routines.index')" variant="ghost">{{ __('Cancel') }}</x-button>
                <x-button type="submit" variant="primary" icon="save">{{ $routine->exists ? __('Save details') : __('Create and open timetable') }}</x-button>
            </div>
        </div>
    </form>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const classSelect = document.getElementById('class_id');
            const sectionSelect = document.getElementById('section_id');
            if (!classSelect || !sectionSelect) return;

            const options = Array.from(sectionSelect.options).filter(option => option.value !== '');
            const chooseSection = @json(__('Choose a section'));
            const chooseClass = @json(__('Choose a class first'));

            const updateSections = () => {
                const classId = classSelect.value;
                let selectedStillAvailable = false;

                options.forEach(option => {
                    const available = classId && option.dataset.classIds.split(',').includes(classId);
                    option.hidden = !available;
                    option.disabled = !available;
                    if (option.value === sectionSelect.value && available) selectedStillAvailable = true;
                });

                if (!selectedStillAvailable) sectionSelect.value = '';
                sectionSelect.options[0].textContent = classId ? chooseSection : chooseClass;
            };

            classSelect.addEventListener('change', updateSections);
            updateSections();
        });
    </script>
@endsection
