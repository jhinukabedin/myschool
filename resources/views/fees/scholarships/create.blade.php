@extends('layouts.app')

@section('title', __('Add New Scholarship'))

@section('content')
    <x-page-header eyebrow="{{ __('Fees') }}" title="{{ __('Add New Scholarship') }}" :subtitle="__('Assign a scholarship or fee waiver to a student for an academic session.')">
        <x-slot:actions>
            <x-button :href="route('fees.scholarships.index')" variant="ghost" icon="arrow-left">{{ __('Back to list') }}</x-button>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('fees.scholarships.store') }}" x-data="{
        discountMethod: 'percent',
        appliesTo: 'all',
        studentId: @js(old('student_id')),
        studentInfo: @js($selectedStudent ? [
            'id' => $selectedStudent->id,
            'name' => $selectedStudent->full_name,
            'admission_no' => $selectedStudent->admission_no,
            'student_code' => $selectedStudent->student_code,
        ] : null),
        query: '',
        searchResults: [],
        searching: false,
        preview: null,
        previewLoading: false,
        async searchStudents() {
            const term = this.query.trim();
            if (term.length < 2) { this.searchResults = []; return; }
            const sessionId = document.getElementById('academic_session_id')?.value;
            this.searching = true;
            try {
                const res = await fetch(@js(route('fees.scholarships.students')) + '?q=' + encodeURIComponent(term) + '&session_id=' + (sessionId || ''), { headers: { 'Accept': 'application/json' } });
                this.searchResults = res.ok ? await res.json() : [];
            } finally { this.searching = false; }
        },
        pickStudent(s) {
            this.studentId = s.id;
            this.studentInfo = s;
            this.searchResults = [];
            this.query = '';
            this.refreshPreview();
        },
        async refreshPreview() {
            if (!this.studentId) { this.preview = null; return; }
            const sessionId = document.getElementById('academic_session_id')?.value;
            const discountValue = document.getElementById('discount_value')?.value;
            if (!sessionId || !discountValue) { this.preview = null; return; }
            const cats = [...document.querySelectorAll('input[name=fee_category_ids\\[\\]]:checked')].map(i => i.value);
            this.previewLoading = true;
            try {
                const res = await fetch(@js(route('fees.scholarships.preview')), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json' },
                    body: new URLSearchParams({
                        _token: document.querySelector('input[name=_token]')?.value || '',
                        student_id: this.studentId,
                        academic_session_id: sessionId,
                        discount_method: this.discountMethod,
                        discount_value: discountValue,
                        applies_to: this.appliesTo,
                        ...(this.appliesTo === 'selected' ? Object.fromEntries(cats.map((c, i) => ['fee_category_ids[' + i + ']', c])) : {}),
                    }).toString(),
                });
                this.preview = res.ok ? await res.json() : null;
            } finally { this.previewLoading = false; }
        },
    }">
        @csrf
        <div class="grid-main-side">
            <div class="stack">
                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">{{ __('Student Information') }}</h3><p class="card-sub">{{ __('Only students enrolled in the selected academic year are eligible') }}</p></div></div>
                    <div class="form-section">
                        <div class="form-grid">
                            <x-select name="academic_session_id" :label="__('Academic Year')" :options="$sessions->pluck('name', 'id')" :selected="$sessionId" required />
                            <div>
                                <label class="field">
                                    <span class="field-label">{{ __('Student') }}<span class="required-mark">*</span></span>
                                    <input class="input" type="search" x-model="query" x-on:input.debounce.300ms="searchStudents()" placeholder="{{ __('Name, Student ID, roll or guardian phone...') }}" autocomplete="off">
                                    <span class="field-hint" x-show="searching">{{ __('Searching...') }}</span>
                                </label>
                                <div class="stack" style="gap:6px;margin-top:6px" x-show="searchResults.length">
                                    <template x-for="s in searchResults" :key="s.id">
                                        <button type="button" class="due-row" style="width:100%;text-align:left;cursor:pointer;border:1px solid var(--line);border-radius:10px;padding:8px 10px;background:transparent" x-on:click="pickStudent(s)">
                                            <div class="due-info">
                                                <span class="table-avatar" x-text="s.name ? s.name.charAt(0) : '?'"></span>
                                                <div>
                                                    <div class="cell-title" x-text="s.name"></div>
                                                    <div class="cell-sub" x-text="'Student ID: ' + (s.student_code || '—') + (s.class ? ' · ' + s.class : '') + (s.guardian_phone ? ' · ' + s.guardian_phone : '')"></div>
                                                </div>
                                            </div>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <input type="hidden" name="student_id" :value="studentId">
                            <div class="span-2" x-show="studentInfo" x-cloak>
                                <div class="info-box" style="margin:0">
                                    {{ __('Selected') }}: <strong x-text="studentInfo?.name"></strong>
                                    <span class="muted" x-text="studentInfo ? ' (Student ID: ' + (studentInfo.student_code || '—') + ')' : ''"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">{{ __('Scholarship Information') }}</h3></div></div>
                    <div class="form-section">
                        <div class="form-grid">
                            <x-input name="scholarship_name" :label="__('Scholarship Name')" required placeholder="Merit Scholarship" />
                            <x-select name="scholarship_type" :label="__('Scholarship Type')" :options="$types" :selected="'merit'" :placeholder="false" required />
                            <div class="span-2"><x-textarea name="description" :label="__('Description')" rows="2" /></div>
                            <div class="span-2"><x-textarea name="reason" :label="__('Reason for Scholarship')" rows="2" /></div>
                            <x-input name="start_date" :label="__('Start Date')" type="date" required />
                            <x-input name="end_date" :label="__('End Date')" type="date" required />
                            <x-select name="status" :label="__('Status')" :options="['pending' => __('Pending Approval'), 'draft' => __('Draft'), 'active' => __('Active (approve now)')]" :selected="'pending'" :placeholder="false" required />
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">{{ __('Discount Configuration') }}</h3><p class="card-sub">{{ __('Percentage is calculated against the eligible fee; fixed is capped at the fee') }}</p></div></div>
                    <div class="form-section">
                        <div class="form-grid">
                            <x-select name="discount_method" :label="__('Discount Method')" :options="['percent' => __('Percentage (%)'), 'fixed' => __('Fixed Amount')]" :selected="'percent'" :placeholder="false" x-model="discountMethod" x-on:change="refreshPreview()" required />
                            <x-input name="discount_value" :label="__('Discount Value')" id="discount_value" type="number" step="0.01" min="0.01" x-on:input.debounce.500ms="refreshPreview()" required />
                            <x-input name="maximum_discount_amount" :label="__('Maximum Discount Amount (optional)')" type="number" step="0.01" min="0" />
                            <x-select name="discount_frequency" :label="__('Frequency')" :options="$frequencies" :selected="'monthly'" :placeholder="false" required />
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">{{ __('Applicable Fee Categories') }}</h3></div></div>
                    <div class="form-section">
                        <div class="form-grid">
                            <x-select name="applies_to" :label="__('Applies to')" :options="['all' => __('All Applicable Fees'), 'selected' => __('Selected Fee Categories')]" :selected="'all'" :placeholder="false" x-model="appliesTo" x-on:change="refreshPreview()" required />
                        </div>
                        <div class="stack" style="gap:6px;margin-top:10px" x-show="appliesTo === 'selected'" x-cloak>
                            @foreach ($categories as $category)
                                <label class="due-row" style="cursor:pointer;align-items:center;gap:10px">
                                    <input type="checkbox" name="fee_category_ids[]" value="{{ $category->id }}" x-on:change="refreshPreview()">
                                    <div class="due-info"><div><div class="cell-title">{{ $category->name }}</div><div class="cell-sub">{{ $category->type }}</div></div></div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="stack">
                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">{{ __('Discount Preview') }}</h3><p class="card-sub">{{ __('Live preview from the actual fee structure — no hardcoded amounts') }}</p></div></div>
                    <div class="card-body">
                        <p class="muted" style="font-size:12px" x-show="!preview && !previewLoading">{{ __('Pick a student and enter a discount to see the preview.') }}</p>
                        <p class="muted" style="font-size:12px" x-show="previewLoading">{{ __('Loading preview...') }}</p>
                        <template x-if="preview">
                            <div>
                                <table class="data-table">
                                    <thead><tr><th>{{ __('Fee') }}</th><th class="num">{{ __('Original') }}</th><th class="num">{{ __('Discount') }}</th><th class="num">{{ __('Payable') }}</th></tr></thead>
                                    <tbody>
                                        <template x-for="row in preview.rows" :key="row.category">
                                            <tr>
                                                <td><div class="cell-title" x-text="row.category"></div></td>
                                                <td class="num" x-text="row.original"></td>
                                                <td class="num" x-text="'-' + row.discount_amount"></td>
                                                <td class="num" x-text="row.payable"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td><strong>{{ __('Total') }}</strong></td>
                                            <td class="num"><strong x-text="preview.totals.original"></strong></td>
                                            <td class="num"><strong x-text="'-' + preview.totals.discount"></strong></td>
                                            <td class="num"><strong x-text="preview.totals.payable"></strong></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="card">
                    <div class="form-section">
                        <label class="field" style="cursor:pointer;flex-direction:row;align-items:center;gap:8px">
                            <input type="checkbox" name="apply_to_existing" value="1">
                            <span>{{ __('Apply Scholarship to Existing Unpaid Invoices') }}</span>
                        </label>
                        <p class="field-hint">{{ __('Only unpaid rows are recalculated. Paid invoices and payment history are never changed automatically.') }}</p>
                        <div class="stack" style="margin-top:12px">
                            <x-button type="submit" variant="primary" icon="graduation-cap" class="btn-block">{{ __('Save Scholarship') }}</x-button>
                            <x-button :href="route('fees.scholarships.index')" variant="ghost" class="btn-block">{{ __('Cancel') }}</x-button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
