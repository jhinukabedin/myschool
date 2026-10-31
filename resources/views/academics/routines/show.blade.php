@extends('layouts.app')

@section('title', 'Class Routine')

@section('content')
    <x-page-header eyebrow="Academic management" :title="($routine->class?->name ?? 'Class').' - Section '.($routine->section?->name ?? '')"
        :subtitle="'Official timetable for the '.($routine->academicSession?->name ?? 'current').' academic year. Days run down the rows and time slots run across the columns.'">
        <x-slot:actions>
            @can('routines.edit')
                <x-button :href="route('routines.settings')" variant="secondary" icon="clock">Days &amp; time slots</x-button>
                <x-button :href="route('routines.edit', $routine)" variant="secondary" icon="edit">Edit details</x-button>
            @endcan
            @can('routines.copy')
                <x-button :href="route('routines.copy', $routine)" variant="secondary" icon="clipboard-list">Copy</x-button>
            @endcan
            @can('routines.print')
                <x-button :href="route('routines.print', $routine)" variant="secondary" icon="printer" target="_blank" rel="noopener">Print</x-button>
                <x-button :href="route('routines.pdf', $routine)" variant="secondary" icon="download" target="_blank" rel="noopener">PDF</x-button>
            @endcan
            @can('routines.delete')
                <form method="POST" action="{{ route('routines.destroy', $routine) }}" onsubmit="return confirm('Delete this routine and every timetable cell in it?')">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="danger" icon="trash">Delete</x-button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card" style="margin-bottom:16px">
        <div class="card-body routine-summary">
            <div>
                <span class="receipt-label">Academic year</span>
                <strong>{{ $routine->academicSession?->name ?? '-' }}</strong>
            </div>
            <div>
                <span class="receipt-label">Class</span>
                <strong>{{ $routine->class?->name ?? '-' }}</strong>
            </div>
            <div>
                <span class="receipt-label">Section</span>
                <strong>{{ $routine->section?->name ?? '-' }}</strong>
            </div>
            <div>
                <span class="receipt-label">Effective from</span>
                <strong>{{ $routine->effectiveDate()->format('d M Y') }}</strong>
            </div>
            <div>
                <span class="receipt-label">Timetable cells</span>
                <strong>{{ $entryMap->count() }}</strong>
            </div>
            @if ($routine->effective_to)
                <div>
                    <span class="receipt-label">Effective to</span>
                    <strong>{{ $routine->effective_to->format('d M Y') }}</strong>
                </div>
            @endif
        </div>
        @if ($routine->notes)
            <div class="card-body" style="border-top:1px solid var(--line)">
                <span class="receipt-label">Notes</span>
                <p style="margin:4px 0 0">{{ $routine->notes }}</p>
            </div>
        @endif
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Weekly timetable</h3>
                <p class="card-sub">
                    Days are rows, periods are columns. Lunch and break columns repeat in every day row.
                    @can('routines.edit') Click any teaching cell to assign or edit a subject and teacher. @endcan
                </p>
            </div>
            <div class="routine-legend">
                <span><i class="routine-dot routine-dot-class"></i> Teaching period</span>
                <span><i class="routine-dot routine-dot-break"></i> Break / lunch</span>
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
                        <h3 id="routine-cell-title">Assign timetable cell</h3>
                        <p id="routine-cell-subtitle">Choose the subject and teacher for this day and period.</p>
                    </div>
                    <button type="button" class="btn-icon" data-routine-close aria-label="Close dialog">
                        <x-icon name="x" :size="17" />
                    </button>
                </div>

                <form method="POST" action="{{ route('routines.cells.save', $routine) }}" id="routine-cell-form">
                    @csrf
                    <input type="hidden" name="day" id="cell-day" value="{{ old('day') }}">
                    <input type="hidden" name="routine_time_slot_id" id="cell-slot" value="{{ old('routine_time_slot_id') }}">

                    <div class="routine-modal-body">
                        <div class="routine-cell-context">
                            <span class="badge badge-violet" id="cell-context-day">Day</span>
                            <span class="badge badge-info" id="cell-context-time">Period</span>
                            <span class="cell-sub">The day is a row and the period is a column.</span>
                        </div>

                        <div class="form-grid">
                            <x-select name="subject_id" label="Subject" :options="$subjects->pluck('name', 'id')" required help="Only real subjects appear in this list." />
                            <x-select name="staff_id" label="Teacher" :options="$teachers->mapWithKeys(fn ($teacher) => [$teacher->id => $teacher->full_name])" required help="Teachers with an overlapping period are rejected." />
                            <x-input name="classroom" label="Classroom (optional)" placeholder="e.g. Room 204" maxlength="80" help="A room cannot be used by two classes in overlapping periods." />
                            <div class="span-2">
                                <label class="field">
                                    <span class="field-label">Notes (optional)</span>
                                    <textarea class="textarea" name="notes" id="cell-notes" rows="2" maxlength="1000" placeholder="Optional note for this period"></textarea>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="routine-modal-foot">
                        <button type="button" class="btn btn-ghost" data-routine-close>Cancel</button>
                        <button type="submit" class="btn btn-danger" id="routine-cell-clear" formaction="{{ route('routines.cells.clear', $routine) }}" hidden>
                            Clear cell
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <x-icon name="save" :size="15" /> Save cell
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
            timeBadge.textContent = time || ('Period ' + slot);
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

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.hidden) {
                close();
            }
        });

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
