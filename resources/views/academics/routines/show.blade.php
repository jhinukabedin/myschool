@extends('layouts.app')

@section('title', __('Class Routine'))

@section('content')
    <x-page-header eyebrow="{{ __('Academic management') }}" :title="($routine->class?->name ?? __('Class')).' - '.__('Section').' '.($routine->section?->name ?? '')"
        :subtitle="__('Official timetable for the :session academic year. Days run down the rows and time slots run across the columns.', ['session' => $routine->academicSession?->name ?? __('current')])">
        <x-slot:actions>
            @can('routines.edit')
                <x-button :href="route('routines.settings', ['class_id' => $routine->class_id])" variant="secondary" icon="clock">{{ __('Class times') }}</x-button>
                <x-button :href="route('routines.edit', $routine)" variant="secondary" icon="edit">{{ __('Edit details') }}</x-button>
            @endcan
            @can('routines.copy')
                <x-button :href="route('routines.copy', $routine)" variant="secondary" icon="clipboard-list">{{ __('Copy') }}</x-button>
            @endcan
            @can('routines.print')
                <x-button type="button" variant="secondary" icon="printer" data-routine-preview data-preview-url="{{ route('routines.print', ['routine' => $routine, 'embed' => 1]) }}" data-preview-pdf-url="{{ route('routines.pdf', $routine) }}">{{ __('Print') }}</x-button>
                <x-button :href="route('routines.pdf', $routine)" variant="secondary" icon="download">{{ __('Download PDF') }}</x-button>
            @endcan
            @can('routines.edit')
                <form method="POST" action="{{ route('routines.website-toggle', $routine) }}">
                    @csrf
                    <x-button type="submit" :variant="$routine->is_published ? 'secondary' : 'primary'" icon="eye">
                        {{ $routine->is_published ? __('Hide from website') : __('Show on website') }}
                    </x-button>
                </form>
            @endcan
            @can('routines.delete')
                <form method="POST" action="{{ route('routines.destroy', $routine) }}" onsubmit="return confirm(@js(__('Delete this routine and every timetable cell in it?')))">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="danger" icon="trash">{{ __('Delete') }}</x-button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card" style="margin-bottom:16px">
        <div class="card-body routine-summary">
            <div>
                <span class="receipt-label">{{ __('Academic year') }}</span>
                <strong>{{ $routine->academicSession?->name ?? '-' }}</strong>
            </div>
            <div>
                <span class="receipt-label">{{ __('Class') }}</span>
                <strong>{{ $routine->class?->name ?? '-' }}</strong>
            </div>
            <div>
                <span class="receipt-label">{{ __('Section') }}</span>
                <strong>{{ $routine->section?->name ?? '-' }}</strong>
            </div>
            <div>
                <span class="receipt-label">{{ __('Effective from') }}</span>
                <strong>{{ $routine->effectiveDate()->format('d M Y') }}</strong>
            </div>
            <div>
                <span class="receipt-label">{{ __('Timetable cells') }}</span>
                <strong>{{ $entryMap->count() }}</strong>
            </div>
            @if ($routine->effective_to)
                <div>
                    <span class="receipt-label">{{ __('Effective to') }}</span>
                    <strong>{{ $routine->effective_to->format('d M Y') }}</strong>
                </div>
            @endif
        </div>
        @if ($routine->notes)
            <div class="card-body" style="border-top:1px solid var(--line)">
                <span class="receipt-label">{{ __('Notes') }}</span>
                <p style="margin:4px 0 0">{{ $routine->notes }}</p>
            </div>
        @endif
    </div>

    @include('academics.routines._preview-modal')

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">{{ __('Weekly timetable') }}</h3>
                <p class="card-sub">
                    {{ __('Days are rows, periods are columns. Lunch and break columns repeat in every day row.') }}
                    @can('routines.edit') {{ __('Click an empty teaching cell to add a subject and teacher; click a filled cell to edit it.') }} @endcan
                </p>
            </div>
            <div class="routine-legend">
                <span><i class="routine-dot routine-dot-class"></i> {{ __('Teaching period') }}</span>
                <span><i class="routine-dot routine-dot-break"></i> {{ __('Break / lunch') }}</span>
            </div>
        </div>

        @include('academics.routines._timetable', ['editable' => auth()->user()?->can('routines.edit')])
    </div>

    @can('routines.edit')
        <div class="routine-modal" id="routine-cell-modal" hidden>
            <div class="routine-modal-backdrop" data-routine-close></div>
            <div class="routine-modal-card" role="dialog" aria-modal="true" aria-labelledby="routine-cell-title">
                <div class="routine-modal-head">
                    <div>
                        <h3 id="routine-cell-title">{{ __('Assign timetable cell') }}</h3>
                        <p id="routine-cell-subtitle">{{ __('Choose the subject and teacher for this day and period.') }}</p>
                    </div>
                    <button type="button" class="btn-icon" data-routine-close aria-label="{{ __('Close dialog') }}">
                        <x-icon name="x" :size="17" />
                    </button>
                </div>

                <form method="POST" action="{{ route('routines.cells.save', $routine) }}" id="routine-cell-form">
                    @csrf
                    <input type="hidden" name="day" id="cell-day" value="{{ old('day') }}">
                    <input type="hidden" name="routine_time_slot_id" id="cell-slot" value="{{ old('routine_time_slot_id') }}">

                    <div class="routine-modal-body">
                        <div class="routine-cell-context">
                            <span class="badge badge-violet" id="cell-context-day">{{ __('Day') }}</span>
                            <span class="badge badge-info" id="cell-context-time">{{ __('Period') }}</span>
                            <span class="cell-sub">{{ __('The day is a row and the period is a column.') }}</span>
                        </div>

                        <div class="form-grid">
                            <x-select name="subject_id" :label="__('Subject')" :options="$subjects->pluck('name', 'id')" required :help="__('Only real subjects appear in this list.')" />
                            <x-select name="staff_id" :label="__('Teacher')" :options="$teachers->mapWithKeys(fn ($teacher) => [$teacher->id => $teacher->full_name])" required :help="__('Teachers with an overlapping period are rejected.')" />
                            <x-input name="classroom" :label="__('Classroom (optional)')" :placeholder="__('e.g. Room 204')" maxlength="80" :help="__('A room cannot be used by two classes in overlapping periods.')" />
                            <div class="span-2">
                                <label class="field">
                                    <span class="field-label">{{ __('Notes (optional)') }}</span>
                                    <textarea class="textarea" name="notes" id="cell-notes" rows="2" maxlength="1000" placeholder="{{ __('Optional note for this period') }}"></textarea>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="routine-modal-foot">
                        <button type="button" class="btn btn-ghost" data-routine-close>{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-danger" id="routine-cell-clear" formaction="{{ route('routines.cells.clear', $routine) }}" hidden>
                            {{ __('Clear cell') }}
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <x-icon name="save" :size="15" /> {{ __('Save cell') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    <script type="application/json" id="routine-cell-data">{!! json_encode($cellData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
    <script type="application/json" id="routine-cell-old">{!! json_encode([
        'day' => old('day'),
        'slot' => old('routine_time_slot_id'),
        'subject_id' => old('subject_id'),
        'staff_id' => old('staff_id'),
        'classroom' => old('classroom'),
        'notes' => old('notes'),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('routine-cell-modal');

        if (!modal) {
            return;
        }

        const form = document.getElementById('routine-cell-form');
        const dataNode = document.getElementById('routine-cell-data');
        const oldNode = document.getElementById('routine-cell-old');
        const entries = dataNode ? JSON.parse(dataNode.textContent || '{}') : {};
        const old = oldNode ? JSON.parse(oldNode.textContent || '{}') : {};
        const dayInput = document.getElementById('cell-day');
        const slotInput = document.getElementById('cell-slot');
        const subjectInput = form.querySelector('[name="subject_id"]');
        const teacherInput = form.querySelector('[name="staff_id"]');
        const classroomInput = form.querySelector('[name="classroom"]');
        const notesInput = document.getElementById('cell-notes');
        const clearButton = document.getElementById('routine-cell-clear');
        const dayBadge = document.getElementById('cell-context-day');
        const timeBadge = document.getElementById('cell-context-time');
        const periodLabel = @json(__('Period'));
        let lastFocused = null;

        function close() {
            modal.hidden = true;
            document.body.classList.remove('routine-modal-open');
            if (lastFocused) {
                lastFocused.focus();
            }
        }

        function open(day, slot, dayLabel, time) {
            const key = day + '|' + slot;
            const entry = entries[key] || {};

            dayInput.value = day;
            slotInput.value = slot;
            subjectInput.value = entry.subject_id || '';
            teacherInput.value = entry.staff_id || '';
            classroomInput.value = entry.classroom || '';
            notesInput.value = entry.notes || '';
            dayBadge.textContent = dayLabel || day;
            timeBadge.textContent = time || (periodLabel + ' ' + slot);
            clearButton.hidden = !entries[key];

            modal.hidden = false;
            document.body.classList.add('routine-modal-open');
            subjectInput.focus();
        }

        document.querySelectorAll('[data-routine-cell]').forEach(function (button) {
            button.addEventListener('click', function () {
                lastFocused = button;
                open(
                    button.dataset.day,
                    button.dataset.slot,
                    button.dataset.dayLabel,
                    button.dataset.time
                );
            });
        });

        modal.querySelectorAll('[data-routine-close]').forEach(function (button) {
            button.addEventListener('click', close);
        });

        // Bind Escape once: after a Turbo visit the old listener would point at
        // a detached modal, so resolve the current dialog at key time.
        if (!window.__routineCellEscapeBound) {
            window.__routineCellEscapeBound = true;

            document.addEventListener('keydown', function (event) {
                if (event.key !== 'Escape') return;

                const current = document.getElementById('routine-cell-modal');

                if (current && !current.hidden) {
                    current.querySelector('[data-routine-close]')?.click();
                }
            });
        }

        // Reopen the editor with the previous input when the server rejected a
        // cell because of a teacher, room or class conflict.
        if (old.day && old.slot) {
            const trigger = document.querySelector('[data-routine-cell][data-day="' + old.day + '"][data-slot="' + old.slot + '"]');
            lastFocused = trigger;
            open(
                old.day,
                old.slot,
                trigger ? trigger.dataset.dayLabel : old.day,
                trigger ? trigger.dataset.time : ''
            );

            if (old.subject_id) subjectInput.value = old.subject_id;
            if (old.staff_id) teacherInput.value = old.staff_id;
            if (old.classroom) classroomInput.value = old.classroom;
            if (old.notes) notesInput.value = old.notes;
        }
    });
</script>
@endpush
