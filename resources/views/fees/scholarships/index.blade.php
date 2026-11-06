@extends('layouts.app')

@section('title', __('Scholarship'))

@section('content')
    <x-page-header eyebrow="{{ __('Fees') }}" title="{{ __('Scholarship') }}" :subtitle="$session ? __('Grant a percentage or fixed concession per fee for :session. Pick a student to see their open fee rows.', ['session' => $session->name]) : __('No academic session is configured.')">
        <x-slot:actions>
            <x-button :href="route('fees.structures.index')" variant="secondary" icon="wallet">{{ __('Fee Structure') }}</x-button>
            <x-button :href="route('fees.collection.index')" variant="ghost" icon="receipt">{{ __('Fee Collection') }}</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="stat-grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
        <x-stat-card :label="__('Students with scholarship')" :value="$stats['students_with_scholarship']" icon="graduation-cap" tone="collection" :meta="__('This session')" />
        <x-stat-card :label="__('Total scholarship')" :value="money($stats['total_scholarship'])" icon="percent" tone="pending" :meta="__('Conceded this session')" />
        <x-stat-card :label="__('Open fee rows')" :value="$stats['open_rows']" icon="wallet" tone="student" :meta="__('Pending / partial')" />
    </div>

    <div class="grid-main-side">
        <div class="stack">
            <div class="card">
                <div class="card-header">
                    <div><h3 class="card-title">{{ __('Select a student') }}</h3><p class="card-sub">{{ __('Search by name, admission/Student ID or guardian phone, then open the scholarship form') }}</p></div>
                    <form method="GET" class="filter-bar" style="margin:0">
                        <div class="field grow" style="min-width:190px">
                            <input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search students') }}">
                        </div>
                        <div class="field">
                            <select class="select" name="class_id">
                                <option value="">{{ __('All classes') }}</option>
                                @foreach ($classes as $class)
                                    <option value="{{ $class->id }}" @selected(request('class_id') == $class->id)>{{ $class->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if ($selectedStudent)
                            <input type="hidden" name="student_id" value="{{ $selectedStudent->id }}">
                        @endif
                        <x-button type="submit" variant="secondary" icon="filter">{{ __('Filter') }}</x-button>
                    </form>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Class') }}</th><th>{{ __('Guardian') }}</th><th class="actions">{{ __('Action') }}</th></tr></thead>
                        <tbody>
                            @forelse ($enrollments as $enrollment)
                                @php($isSelected = $selectedStudent && $enrollment->student_id === $selectedStudent->id)
                                <tr style="{{ $isSelected ? 'background:rgba(124,92,255,.06)' : '' }}">
                                    <td>
                                        <div class="cell-main">
                                            <span class="table-avatar">{{ Str::substr($enrollment->student?->full_name ?? '?', 0, 1) }}</span>
                                            <div>
                                                <div class="cell-title">{{ $enrollment->student?->full_name }}</div>
                                                <div class="cell-sub">Student ID: {{ $enrollment->student?->student_code }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>{{ $enrollment->class?->name }}</div>
                                        <div class="cell-sub">{{ __('Section') }} {{ $enrollment->section?->name }} · {{ __('Roll') }} {{ $enrollment->roll_no }}</div>
                                    </td>
                                    <td>
                                        <div>{{ $enrollment->student?->primaryGuardian()?->name ?? '—' }}</div>
                                        <div class="cell-sub">{{ $enrollment->student?->primaryGuardian()?->phone }}</div>
                                    </td>
                                    <td class="actions">
                                        @if ($isSelected)
                                            <span class="badge badge-violet">{{ __('Selected') }}</span>
                                        @else
                                            <a href="{{ route('fees.scholarships.index', array_merge(request()->except('student_id'), ['student_id' => $enrollment->student_id])) }}" class="btn btn-primary btn-sm">
                                                <x-icon name="graduation-cap" :size="13" /> {{ __('Scholarship') }}
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="table-empty">{{ __('No students match these filters.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $enrollments->links() }}
            </div>
        </div>

        <div class="stack">
            @if ($selectedStudent && $session)
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">{{ __('Scholarship for') }} {{ $selectedStudent->full_name }}</h3>
                            <p class="card-sub">Student ID: {{ $selectedStudent->student_code }}</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="detail-list">
                            <div class="detail-row"><span class="detail-label">{{ __('Guardian') }}</span><span class="detail-value">{{ $selectedStudent->primaryGuardian()?->name ?? '—' }}</span></div>
                            <div class="detail-row"><span class="detail-label">{{ __('Phone') }}</span><span class="detail-value">{{ $selectedStudent->primaryGuardian()?->phone ?? '—' }}</span></div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('fees.scholarships.apply', $selectedStudent) }}" x-data="{ allChecked: false, scholarshipType: 'percent' }">
                        @csrf
                        <div class="form-section">
                            <p class="field-hint" style="margin:0 0 8px">{{ __('Tick the fees you want to discount, choose % or fixed, then apply. The concession reduces the payable balance on those rows.') }}</p>
                            <div class="stack" style="gap:6px">
                                @forelse ($assignments as $assignment)
                                    <label class="due-row" style="align-items:flex-start;gap:10px;cursor:pointer" x-on:click.prevent="($el.querySelector('input[name=assignment_ids\\\[]\\\']).checked = !$el.querySelector('input[name=assignment_ids\\\[]\\\']).checked); allChecked = [...document.querySelectorAll('input[name=assignment_ids\\\[]\\\']')].every(i => i.checked)">
                                        <input type="checkbox" name="assignment_ids[]" value="{{ $assignment->id }}"@disabled($assignment->balance <= 0) style="margin-top:4px">
                                        <div class="due-info" style="flex:1">
                                            <div>
                                                <div class="cell-title">{{ $assignment->category?->name }} · {{ $assignment->period ?? __('One-time') }}</div>
                                                <div class="cell-sub">
                                                    {{ __('Due') }} {{ $assignment->due_date?->format('d M Y') }}
                                                    · {{ __('Net') }} {{ money($assignment->net_amount) }}
                                                    · {{ __('Paid') }} {{ money($assignment->paid_amount) }}
                                                    · {{ __('Balance') }} {{ money($assignment->balance) }}
                                                    @if ((float) $assignment->scholarship_amount > 0)
                                                        · <span style="color:var(--ok, #0a7d55)">{{ __('Scholarship') }} {{ $assignment->scholarship_label }} · {{ money($assignment->scholarship_amount) }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        @if ($assignment->balance <= 0)
                                            <span class="badge badge-{{ $assignment->status === 'paid' ? 'success' : 'info' }}">{{ ucfirst($assignment->status) }}</span>
                                        @endif
                                    </label>
                                @empty
                                    <p class="muted" style="font-size:12px;margin:0">{{ __('No fee rows assigned for this student in the current session. Assign a fee structure from Fee Collection first.') }}</p>
                                @endforelse
                            </div>

                            @if ($assignments->where('balance', '>', 0)->isNotEmpty())
                                <div class="form-grid" style="margin-top:14px">
                                    <x-select name="scholarship_type" :label="__('Scholarship type')" :options="['percent' => __('Percentage (%)'), 'fixed' => __('Fixed amount'), 'none' => __('Remove scholarship')]" :selected="'percent'" :placeholder="false" x-model="scholarshipType" required />
                                    <x-input name="scholarship_value" :label="__('Value')" type="number" step="0.01" min="0" :max="scholarshipType === 'percent' ? 100 : 9999999" :placeholder="__('e.g. 25 or 200')" />
                                </div>
                                <div class="form-section" style="display:flex;justify-content:flex-end;padding:0;margin-top:14px">
                                    <x-button type="submit" variant="primary" icon="graduation-cap">{{ __('Apply scholarship') }}</x-button>
                                </div>
                            @endif
                        </div>
                    </form>
                </div>
            @else
                <div class="card">
                    <div class="card-body">
                        <p class="muted" style="font-size:13px;margin:0">{{ __('Select a student on the left to grant or remove a scholarship. You can tick one or several fee rows and apply % or a fixed amount in one go.') }}</p>
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header"><div><h3 class="card-title">{{ __('How it works') }}</h3></div></div>
                <div class="card-body">
                    <div class="info-box">{{ __('Scholarship is stored per fee row and is capped at the payable amount. Waiver and scholarship stack without double-counting; the collected revenue stays correct on receipts. Removing a scholarship keeps the audit trail.') }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
