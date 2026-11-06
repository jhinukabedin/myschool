@extends('layouts.app')

@section('title', __('New Admission'))

@section('content')
    <x-page-header eyebrow="{{ __('Students') }}" title="{{ __('New Admission') }}" subtitle="{{ __('Create the student record, guardian profile, class enrolment and optional fee assignment in one step.') }}">
        <x-slot:actions>
            <x-button :href="route('students.index')" variant="secondary" icon="arrow-left">{{ __('All students') }}</x-button>
        </x-slot:actions>
    </x-page-header>

    @if (! $session)
        <div class="alert alert-warning"><span class="alert-icon"><x-icon name="alert-triangle" :size="17" /></span><div>{{ __('No academic session is configured. Ask an administrator to create one first.') }}</div></div>
    @endif

    <form method="POST" action="{{ route('admissions.store') }}" enctype="multipart/form-data" x-data="admissionSiblings()">
        @csrf
        @if ($enquiry)
            <input type="hidden" name="enquiry_id" value="{{ $enquiry->id }}">
        @endif

        <div class="grid-main-side">
            <div class="stack">
                <div class="card">
                    <div class="card-header">
                        <div><h3 class="card-title">{{ __('Student Information') }}</h3><p class="card-sub">{{ __('Official details used on records and result sheets') }}</p></div>
                        <x-badge color="violet">{{ __('Required fields marked *') }}</x-badge>
                    </div>
                    <div class="form-section">
                        <div class="form-section-title">{{ __('Identity') }}</div>
                        <div class="form-grid">
                            <x-input name="first_name" :label="__('Student name (English)')" :value="$enquiry?->student_name" required />
                            <x-input name="name_bn" :label="__('Student name (Bangla)')" placeholder="{{ __('Name in Bangla') }}" />
                            <x-select name="gender" :label="__('Gender')" :options="['male' => __('Male'), 'female' => __('Female'), 'other' => __('Other')]" required />
                            <x-input name="date_of_birth" :label="__('Date of birth')" type="date" required />
                            <x-input name="blood_group" :label="__('Blood group')" placeholder="e.g. B+" />
                            <x-input name="religion" :label="__('Religion')" />
                            <x-input name="nationality" :label="__('Nationality')" value="Bangladeshi" />
                            <x-input name="photo" :label="__('Student photo')" type="file" :help="__('JPG, PNG or WebP, max 2 MB.')" />
                        </div>
                    </div>
                    <div class="form-section">
                        <div class="form-section-title">{{ __('Contact') }}</div>
                        <div class="form-grid">
                            <x-input name="phone" :label="__('Student phone')" />
                            <x-input name="email" :label="__('Student email')" type="email" />
                            <div class="span-2"><x-input name="address" :label="__('Address')" /></div>
                            <x-input name="city" :label="__('City')" value="Dhaka" />
                            <x-input name="state" :label="__('State / Division')" value="Dhaka" />
                            <x-input name="postal_code" :label="__('Postal code')" />
                            <x-input name="previous_school" :label="__('Previous school')" />
                            <div class="span-2"><x-textarea name="medical_notes" :label="__('Medical notes')" rows="2" :placeholder="__('Allergies, medication or accessibility needs')" /></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div><h3 class="card-title">{{ __('Guardian Information') }}</h3><p class="card-sub">{{ __('Primary contact for fees and communications') }}</p></div>
                    </div>
                    <div class="form-section" style="border-bottom:1px solid var(--line)">
                        <div class="form-grid">
                            <x-select name="has_sibling" :label="__('Brother or sister already admitted?')" :options="['0' => __('No'), '1' => __('Yes')]" :selected="old('has_sibling', '0')" :placeholder="false" x-model="hasSibling" x-on:change="onToggle()" required />
                            <div class="span-2">
                                <p class="field-hint" style="margin:0">{{ __('Select Yes and pick the enrolled sibling below. Guardian details auto-fill from the sibling; you can still change them.') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="form-section" style="border-bottom:1px solid var(--line)" x-show="hasSibling === '1'" x-cloak>
                        <input type="hidden" name="sibling_student_id" :value="selected ? selected.id : ''">

                        <label class="field">
                            <span class="field-label">{{ __('Search sibling student') }}</span>
                            <input
                                class="input"
                                type="search"
                                x-model="query"
                                x-on:input.debounce.300ms="runSearch()"
                                x-on:focus="if (results.length) open = true"
                                :placeholder="'Type name, admission no or ID...'"
                                autocomplete="off"
                            >
                            <span class="field-hint" x-show="searching">{{ __('Searching...') }}</span>
                        </label>

                        <div class="stack" style="gap:6px;margin-top:8px" x-show="open && !searching" x-cloak>
                            <template x-for="s in results" :key="s.id">
                                <button type="button" class="due-row" style="width:100%;text-align:left;cursor:pointer;border:1px solid var(--line);border-radius:10px;padding:8px 10px;background:transparent" x-on:click="pick(s)">
                                    <div class="due-info">
                                        <span class="table-avatar" x-text="s.name ? s.name.charAt(0) : '?'"></span>
                                        <div>
                                            <div class="cell-title" x-text="s.name"></div>
                                            <div class="cell-sub" x-text="(s.student_code ? 'Student ID: ' + s.student_code + ' · ' : '') + (s.class ? s.class + (s.section ? ' · ' + s.section : '') : '')"></div>
                                        </div>
                                    </div>
                                    <div class="due-amount" x-text="(s.student_code ? 'ID: ' + s.student_code : '') + (s.roll ? (s.student_code ? ' · ' : '') + labels.roll + ' ' + s.roll : '')"></div>
                                </button>
                            </template>
                            <p class="muted" style="font-size:12px;margin:0" x-show="results.length === 0">{{ __('No students found.') }}</p>
                        </div>

                        <div class="info-box" style="margin-top:12px" x-show="selected" x-cloak>
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                                <div>
                                    <strong x-text="selected ? selected.name : ''"></strong>
                                    <div class="cell-sub" x-text="selected ? ('Student ID: ' + (selected.student_code || '—')) : ''"></div>
                                </div>
                                <button type="button" class="btn btn-ghost btn-sm" x-on:click="clear()">{{ __('Change') }}</button>
                            </div>
                            <p style="font-size:12px;margin:6px 0 0" x-text="message"></p>
                        </div>
                    </div>
                    <div class="form-section">
                        <div class="form-grid">
                            <x-input name="guardian_name" :label="__('Guardian name')" :value="$enquiry?->guardian_name" required />
                            <x-select name="guardian_relation" :label="__('Relationship')" :options="['Father' => __('Father'), 'Mother' => __('Mother'), 'Guardian' => __('Guardian'), 'Other' => __('Other')]" required />
                            <x-input name="guardian_phone" :label="__('Guardian phone')" :value="$enquiry?->phone" required :help="__('This mobile number becomes the parent portal username. A sibling with the same number shares one parent account.')" />
                            <x-input name="guardian_email" :label="__('Guardian email')" type="email" :value="$enquiry?->email" />
                            <x-input name="guardian_occupation" :label="__('Occupation')" />
                            <x-input name="guardian_address" :label="__('Guardian address')" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="stack">
                <div class="card">
                    <div class="card-header">
                        <div><h3 class="card-title">{{ __('Enrolment') }}</h3><p class="card-sub">{{ __('Academic placement for this admission') }}</p></div>
                    </div>
                    <div class="form-section">
                        <div class="form-grid">
                            <div class="span-2">
                                <x-select name="academic_session_id" :label="__('Academic session')" :options="$sessions->pluck('name', 'id')" :selected="$session?->id" required />
                            </div>
                            <div class="span-2">
                                <x-select name="class_id" :label="__('Class')" :options="$classes->pluck('name', 'id')" :selected="$enquiry?->class_id" required />
                            </div>
                            <div class="span-2">
                                <x-select name="section_id" :label="__('Section')" :options="$sections->pluck('name', 'id')" required />
                            </div>
                            <div class="span-2">
                                <x-input name="roll_no" :label="__('Roll number')" type="number" :help="__('Leave blank to generate the next roll number.')" />
                            </div>
                            <div class="span-2">
                                <x-input name="admission_date" :label="__('Admission date')" type="date" :value="now()->toDateString()" required />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div><h3 class="card-title">{{ __('Fees') }}</h3><p class="card-sub">{{ __('Optionally assign the class fee structure now') }}</p></div>
                    </div>
                    <div class="form-section">
                        <x-select name="fee_structure_id" :label="__('Fee structure')" :options="$structures->pluck('name', 'id')" :placeholder="__('Assign later')" :help="__('Monthly items generate one due row per month of the session. Optional items can be added from the collection page.')" />
                        <div class="info-box" style="margin-top:14px">
                            {{ __('Financial records are never hard-deleted. Receipts must be reversed to preserve a complete audit trail.') }}
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="form-section">
                        <div class="form-section-title">{{ __('Save admission') }}</div>
                        <p class="muted" style="margin:0 0 10px">{{ __('The student ID, portal accounts, guardian link, enrolment and fee assignment are written in one database transaction.') }}</p>
                        <div class="info-box" style="margin:0 0 14px">
                            {!! __('A permanent Student ID is generated as :format (for example 2603001 for KG in 2026). The student and guardian receive automatic portal logins; the credentials are shown once on the next screen.', ['format' => '<strong>YY + class code + serial</strong>']) !!}
                        </div>
                        <div class="stack">
                            <x-button type="submit" variant="primary" icon="user-plus" class="btn-block" :disabled="! $session">{{ __('Admit student') }}</x-button>
                            <x-button :href="route('students.index')" variant="ghost" class="btn-block">{{ __('Cancel') }}</x-button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <script>
        function admissionSiblings() {
            return {
                hasSibling: '{{ old('has_sibling', '0') }}',
                labels: {
                    guardianFilled: @js(__('Guardian details auto-filled from the sibling. You can still change them.')),
                    guardianMissing: @js(__('No guardian found for this student. Fill the guardian details manually.')),
                    roll: @js('Roll'),
                },
                query: '',
                results: [],
                selected: null,
                searching: false,
                open: false,
                message: '',

                init() {
                    @if ($preselectedSibling)
                    const pre = @js([
                        'id' => $preselectedSibling->id,
                        'name' => $preselectedSibling->full_name,
                        'admission_no' => $preselectedSibling->admission_no,
                        'student_code' => $preselectedSibling->student_code,
                        'guardian' => $preselectedSibling->guardians->first() ? [
                            'name' => $preselectedSibling->guardians->first()->name,
                            'relation' => $preselectedSibling->guardians->first()->relation,
                            'phone' => $preselectedSibling->guardians->first()->phone,
                            'email' => $preselectedSibling->guardians->first()->email,
                            'occupation' => $preselectedSibling->guardians->first()->occupation,
                            'address' => $preselectedSibling->guardians->first()->address,
                        ] : null,
                    ]);
                    this.selected = pre;
                    this.hasSibling = '1';
                    this.fillGuardian(pre.guardian, false);
                    @endif
                },

                onToggle() {
                    if (this.hasSibling !== '1') {
                        this.selected = null;
                        this.query = '';
                        this.results = [];
                        this.open = false;
                        this.message = '';
                    }
                },

                runSearch() {
                    if (this.hasSibling !== '1') return;
                    const term = this.query.trim();
                    if (term.length < 2) { this.results = []; this.open = false; return; }

                    this.searching = true;
                    fetch('{{ route('admissions.sibling-search') }}?q=' + encodeURIComponent(term), {
                        headers: { 'Accept': 'application/json' },
                    })
                        .then((response) => (response.ok ? response.json() : []))
                        .then((data) => { this.results = data; this.open = true; })
                        .catch(() => { this.results = []; })
                        .finally(() => { this.searching = false; });
                },

                pick(sibling) {
                    this.selected = sibling;
                    this.query = '';
                    this.results = [];
                    this.open = false;
                    this.fillGuardian(sibling.guardian, true);
                },

                clear() {
                    this.selected = null;
                    this.message = '';
                },

                fillGuardian(guardian, overwrite) {
                    if (!guardian) {
                        this.message = this.labels.guardianMissing;
                        return;
                    }

                    this.message = this.labels.guardianFilled;

                    const values = {
                        guardian_name: guardian.name,
                        guardian_phone: guardian.phone,
                        guardian_email: guardian.email,
                        guardian_occupation: guardian.occupation,
                        guardian_address: guardian.address,
                    };

                    Object.entries(values).forEach(([id, value]) => {
                        const el = document.getElementById(id);
                        if (!el) return;
                        if (overwrite || !el.value) el.value = value || '';
                    });

                    const relation = document.getElementById('guardian_relation');
                    if (relation && Array.from(relation.options).some((option) => option.value === guardian.relation)) {
                        relation.value = guardian.relation;
                    }
                },
            };
        }
    </script>
@endsection
