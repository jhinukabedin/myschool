@extends('layouts.app')

@section('title', $exam->exists ? 'Edit Examination' : 'New Examination')

@section('content')
    @php($initialTab = $errors->has('distribution') || $errors->has('confirm_distribution_change') ? 'subjects' : 'general')
    <x-page-header eyebrow="Examinations" :title="$exam->exists ? 'Edit Examination' : 'New Examination'" subtitle="Set the exam details, class subjects, marks distribution, and schedule.">
        <x-slot:actions><x-button :href="route('exams.index')" variant="secondary" icon="arrow-left">Examinations</x-button></x-slot:actions>
    </x-page-header>

    <form method="POST" novalidate action="{{ $exam->exists ? route('exams.update', $exam) : route('exams.store') }}" x-data="examDistributionEditor(@js($distributionConfigs), @js($distributionDefaults), @js($selectedClasses), @js($classes->pluck('name', 'id')), @js($selectedSessionId), {{ $existingMarksCount }}, {{ $distributionLocked ? 'true' : 'false' }}, @js($initialTab))">
        @csrf
        @if ($exam->exists) @method('PUT') @endif

        <nav class="exam-form-tabs" role="tablist" aria-label="Examination setup">
            <button type="button" :class="activeTab === 'general' ? 'active' : ''" @click="activeTab='general'">General Information</button>
            <button type="button" :class="activeTab === 'subjects' ? 'active' : ''" @click="activeTab='subjects'">Subject &amp; Marks</button>
            <button type="button" :class="activeTab === 'schedule' ? 'active' : ''" @click="activeTab='schedule'">Exam Schedule</button>
            <button type="button" :class="activeTab === 'settings' ? 'active' : ''" @click="activeTab='settings'">Settings</button>
        </nav>

        <section class="card" x-show="activeTab === 'general'" x-cloak>
            <div class="card-header"><div><h3 class="card-title">General Information</h3><p class="card-sub">Names are unique within an academic session.</p></div></div>
            <div class="form-section"><div class="form-grid">
                <div class="span-2"><x-input name="name" label="Examination name" :value="$exam->name" required placeholder="e.g. First Term Examination 2026" /></div>
                <x-select name="academic_session_id" label="Academic session" :options="$sessions->pluck('name', 'id')" :selected="$exam->academic_session_id" x-on:change="selectedSession = $event.target.value" required />
                <x-select name="exam_type" label="Examination type" :options="['term' => 'Term examination', 'mid_term' => 'Mid-term', 'final' => 'Final', 'annual' => 'Annual', 'ssc' => 'SSC / equivalent', 'hsc' => 'HSC / equivalent', 'unit_test' => 'Unit test', 'other' => 'Other']" :selected="$exam->exam_type" required />
                <x-input name="start_date" label="Start date" type="date" :value="$exam->start_date?->toDateString()" required />
                <x-input name="end_date" label="End date" type="date" :value="$exam->end_date?->toDateString()" required help="Must be on or after the start date." />
            </div></div>
        </section>

        <section class="card" x-show="activeTab === 'subjects'" x-cloak>
            <div class="card-header"><div><h3 class="card-title">Select Classes &amp; Configure Subjects</h3><p class="card-sub">Assigned subjects load automatically. Exam values are saved as historical snapshots.</p></div><button type="button" class="btn btn-secondary" @click="loadDefaults()" :disabled="locked">Load Default Configuration</button></div>
            @if($distributionLocked)<div class="warning-box" style="margin:16px">This examination has published snapshots. Its marks distribution is locked to preserve historical results.</div>@endif
            <div class="form-section">
                <div class="distribution-class-list">
                    @foreach($classes as $classOption)
                        <label class="checkbox"><input type="checkbox" name="selected_classes[]" value="{{ $classOption->id }}" x-model="selectedClasses" @disabled($distributionLocked)><span>{{ $classOption->name }}</span></label>
                    @endforeach
                </div>
                <template x-if="visibleConfigs().length === 0"><div class="empty-hint">Select one or more classes to load their assigned subjects.</div></template>
                <template x-for="entry in visibleConfigs()" :key="entry.class_id + '_' + entry.subject_id">
                    <article class="exam-subject-config">
                        <header><div><span class="eyebrow" x-text="className(entry.class_id)"></span><h4 x-text="entry.subject_name"></h4></div><div class="exam-config-source"><span class="badge badge-violet" x-text="entry.source"></span><button type="button" class="btn btn-ghost btn-sm" :disabled="locked" @click="resetToDefault(entry)">Reset to Default</button></div></header>
                        <div class="form-grid distribution-mark-fields">
                            <label class="field"><span class="field-label">Full marks</span><input class="input" type="number" min="0.01" max="999" step="0.01" :name="fieldName(entry,'full_marks')" x-model="entry.full_marks" :disabled="locked" required></label>
                            <label class="field"><span class="field-label">Overall pass marks</span><input class="input" type="number" min="0" max="999" step="0.01" :name="fieldName(entry,'pass_marks')" x-model="entry.pass_marks" :disabled="locked" required></label>
                            <div class="distribution-total"><span>Component total</span><strong x-text="componentTotal(entry)"></strong></div>
                        </div>
                        <div class="exam-component-editor">
                            <div class="distribution-component-head"><div class="field-label">Components</div><button type="button" class="btn btn-secondary btn-sm" :disabled="locked" @click="entry.components.push({name:'',full_marks:'',pass_marks:0,requires_pass:false})">Add Component</button></div>
                            <template x-for="(component, index) in entry.components" :key="index">
                                <div class="form-grid distribution-component-row">
                                    <input class="input" type="text" maxlength="80" placeholder="Component name" :name="componentName(entry,index,'name')" x-model="component.name" :disabled="locked" required>
                                    <input class="input" type="number" min="0.01" max="999" step="0.01" placeholder="Full marks" :name="componentName(entry,index,'full_marks')" x-model="component.full_marks" :disabled="locked" required>
                                    <input class="input" type="number" min="0" max="999" step="0.01" placeholder="Pass marks" :name="componentName(entry,index,'pass_marks')" x-model="component.pass_marks" :disabled="locked">
                                    <label class="checkbox"><input type="checkbox" value="1" :name="componentName(entry,index,'requires_pass')" x-model="component.requires_pass" :disabled="locked"><span>Must pass</span></label>
                                    <button type="button" class="btn-icon" :disabled="locked" @click="entry.components.splice(index,1)" title="Remove component"><x-icon name="trash" :size="15" /></button>
                                </div>
                            </template>
                            <p class="field-hint" x-show="entry.components.length">Component full marks must equal subject full marks. “Must pass” is separate from overall grade percentage.</p>
                        </div>
                    </article>
                </template>
            </div>
            @if($existingMarksCount > 0 && ! $distributionLocked)
                <div class="form-section warning-box" style="margin:16px">
                    <strong>Marks already exist for this exam.</strong> Changing any marks distribution requires explicit confirmation.
                    <label class="checkbox" style="margin-top:10px"><input type="checkbox" name="confirm_distribution_change" value="1"><span>I confirm the distribution change for existing marks.</span></label>
                    @error('confirm_distribution_change')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            @endif
            @error('distribution')<div class="field-error" style="padding:0 18px 16px">{{ $message }}</div>@enderror
        </section>

        <section class="card" x-show="activeTab === 'schedule'" x-cloak>
            <div class="card-header"><div><h3 class="card-title">Exam Schedule</h3><p class="card-sub">Set an individual date and time for every class subject.</p></div></div>
            <div class="form-section">
                <template x-if="visibleConfigs().length === 0"><div class="empty-hint">Select classes in Subject &amp; Marks to configure the schedule.</div></template>
                <template x-for="entry in visibleConfigs()" :key="entry.class_id + '_' + entry.subject_id + '_schedule'">
                    <article class="exam-schedule-row"><div><span class="eyebrow" x-text="className(entry.class_id)"></span><strong x-text="entry.subject_name"></strong></div><div class="form-grid exam-schedule-fields">
                        <label class="field"><span class="field-label">Exam date</span><input class="input" type="date" :name="fieldName(entry,'exam_date')" x-model="entry.exam_date" :disabled="locked"></label>
                        <label class="field"><span class="field-label">Start time</span><input class="input" type="time" :name="fieldName(entry,'start_time')" x-model="entry.start_time" :disabled="locked"></label>
                        <label class="field"><span class="field-label">Duration (minutes)</span><input class="input" type="number" min="1" max="600" :name="fieldName(entry,'duration_minutes')" x-model="entry.duration_minutes" :disabled="locked"></label>
                    </div></article>
                </template>
            </div>
        </section>

        <section class="card" x-show="activeTab === 'settings'" x-cloak>
            <div class="card-header"><div><h3 class="card-title">Settings</h3><p class="card-sub">Grade scheme percentage boundaries remain separate from marks components.</p></div></div>
            <div class="form-section"><div class="form-grid">
                <x-select name="grading_scheme_id" label="Grading scheme" :options="$gradingSchemes->pluck('name', 'id')->prepend('Institution default', '')" :selected="$exam->grading_scheme_id" help="Published results store the chosen grade boundaries." />
                <x-select name="status" label="Status" :options="['draft' => 'Draft', 'published' => 'Published', 'completed' => 'Completed']" :selected="$exam->status" required help="Publishing and reopening are managed from the examination page." />
            </div></div>
        </section>

        <div class="card form-section" style="display:flex;justify-content:flex-end;gap:8px"><x-button :href="route('exams.index')" variant="ghost">Cancel</x-button><button type="submit" class="btn btn-primary"><x-icon name="save" :size="16" /><span x-text="activeTab === 'subjects' ? 'Save Configuration' : '{{ $exam->exists ? 'Save examination' : 'Create examination' }}'"></span></button></div>
    </form>
@endsection

@push('scripts')
<script>
window.examDistributionEditor = (initial, defaults, selected, classNames, sessionId, marksCount, locked, initialTab) => ({
    activeTab: initialTab, configurations: initial, defaults, selectedClasses: selected.map(String), classNames, selectedSession: String(sessionId), marksCount, locked,
    visibleConfigs() { return Object.values(this.configurations).filter(row => this.selectedClasses.includes(String(row.class_id)) && String(row.session_id) === this.selectedSession); },
    className(id) { return this.classNames[id] || `Class ${id}`; },
    fieldName(row, field) { return `distribution[${row.class_id}][${row.subject_id}][${field}]`; },
    componentName(row, index, field) { return `distribution[${row.class_id}][${row.subject_id}][components][${index}][${field}]`; },
    componentTotal(row) { return row.components.reduce((total, item) => total + (parseFloat(item.full_marks) || 0), 0).toFixed(2); },
    resetToDefault(row) {
        if (this.marksCount && !window.confirm('Resetting this subject may change marks already entered. Continue?')) return;
        const key = `${row.class_id}_${row.subject_id}`;
        const schedule = { exam_date: row.exam_date, start_time: row.start_time, duration_minutes: row.duration_minutes };
        this.configurations[key] = { ...JSON.parse(JSON.stringify(this.defaults[key])), ...schedule };
    },
    loadDefaults() {
        if (this.marksCount && !window.confirm('Some marks are already entered. Loading defaults changes their distribution and requires confirmation. Continue?')) return;
        const schedules = Object.fromEntries(Object.entries(this.configurations).map(([key, row]) => [key, { exam_date: row.exam_date, start_time: row.start_time, duration_minutes: row.duration_minutes }]));
        this.configurations = JSON.parse(JSON.stringify(this.defaults));
        for (const [key, schedule] of Object.entries(schedules)) if (this.configurations[key]) Object.assign(this.configurations[key], schedule);
    }
});
</script>
@endpush
