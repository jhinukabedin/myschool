@extends('layouts.app')

@section('title', __('Scholarship Details'))

@section('content')
    <x-page-header eyebrow="{{ __('Fees') }}" :title="$scholarship->scholarship_name" :subtitle="$scholarship->student?->full_name.' · '.$scholarship->type_label.' · '.$scholarship->status_label">
        <x-slot:actions>
            <x-button :href="route('fees.scholarships.history', $scholarship)" variant="secondary" icon="clock">{{ __('Discount History') }}</x-button>
            <x-button :href="route('fees.scholarships.index')" variant="ghost" icon="arrow-left">{{ __('Back') }}</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="stat-grid" style="grid-template-columns:repeat(4,minmax(0,1fr))">
        <x-stat-card :label="__('Discount')" :value="$scholarship->discount_method === 'percent' ? rtrim(rtrim(number_format((float) $scholarship->discount_value, 2, '.', ''), '0'), '.').'%' : money($scholarship->discount_value)" icon="percent" tone="collection" :meta="$scholarship->applies_to === 'all' ? __('All fees') : $scholarship->items->count().' '.__('categories')" />
        <x-stat-card :label="__('Period')" :value="$scholarship->start_date->format('d M Y').' – '.$scholarship->end_date->format('d M Y')" icon="calendar" tone="pending" :meta="__('Effective period')" />
        <x-stat-card :label="__('Frequency')" :value="\App\Models\StudentScholarship::FREQUENCIES[$scholarship->discount_frequency] ?? $scholarship->discount_frequency" icon="clock" tone="student" :meta="__('How often it applies')" />
        <x-stat-card :label="__('Adjustments')" :value="$scholarship->adjustments->count()" icon="receipt" tone="collection" :meta="__('Invoice adjustments')" />
    </div>

    <div class="grid-main-side">
        <div class="stack">
            <div class="card">
                <div class="card-header"><div><h3 class="card-title">{{ __('Unpaid Invoices') }}</h3><p class="card-sub">{{ __('Eligible rows that will receive this scholarship') }}</p></div></div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>{{ __('Fee') }}</th><th>{{ __('Period') }}</th><th>{{ __('Due') }}</th><th class="num">{{ __('Net') }}</th><th class="num">{{ __('Paid') }}</th><th class="num">{{ __('Balance') }}</th><th class="num">{{ __('Proposed Discount') }}</th><th class="num">{{ __('New Payable') }}</th></tr></thead>
                        <tbody>
                            @forelse ($unpaid as $row)
                                @php($proposed = $scholarship->discountFor($row))
                                <tr>
                                    <td><div class="cell-title">{{ $row->category?->name }}</div><div class="cell-sub">{{ $row->period ?? __('One-time') }}</div></td>
                                    <td>{{ $row->period ?? '—' }}</td>
                                    <td>{{ $row->due_date?->format('d M Y') }}</td>
                                    <td class="num">{{ money($row->net_amount) }}</td>
                                    <td class="num">{{ money($row->paid_amount) }}</td>
                                    <td class="num">{{ money($row->balance) }}</td>
                                    <td class="num text-success">{{ money($proposed) }}</td>
                                    <td class="num"><strong>{{ money(max($row->paid_amount, $row->net_amount - $proposed)) }}</strong></td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="table-empty">{{ __('No unpaid invoices for this scholarship.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($unpaid->isNotEmpty())
                    <div class="form-section" style="display:flex;justify-content:flex-end">
                        <form method="POST" action="{{ route('fees.scholarships.apply-to-invoices', $scholarship) }}">
                            @csrf
                            <x-button type="submit" variant="primary" icon="check">{{ __('Apply Scholarship to Existing Unpaid Invoices') }}</x-button>
                        </form>
                    </div>
                @endif
            </div>

            <div class="card">
                <div class="card-header"><div><h3 class="card-title">{{ __('Invoice Adjustments') }}</h3></div></div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Invoice') }}</th><th class="num">{{ __('Original') }}</th><th class="num">{{ __('Discount') }}</th><th class="num">{{ __('Payable') }}</th><th>{{ __('Reason') }}</th></tr></thead>
                        <tbody>
                            @forelse ($scholarship->adjustments as $adjustment)
                                <tr>
                                    <td>{{ $adjustment->created_at->format('d M Y') }}</td>
                                    <td>#{{ $adjustment->student_fee_assignment_id }}</td>
                                    <td class="num">{{ money($adjustment->original_amount) }}</td>
                                    <td class="num text-success">{{ money($adjustment->discount_amount) }}</td>
                                    <td class="num">{{ money($adjustment->payable_amount) }}</td>
                                    <td class="muted" style="font-size:12px">{{ $adjustment->adjustment_reason }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="table-empty">{{ __('No adjustments recorded yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <div class="card-header"><div><h3 class="card-title">{{ __('Student') }}</h3></div></div>
                <div class="card-body">
                    <div class="detail-list">
                        <div class="detail-row"><span class="detail-label">{{ __('Name') }}</span><span class="detail-value">{{ $scholarship->student?->full_name }}</span></div>
                        <div class="detail-row"><span class="detail-label">{{ __('Student ID') }}</span><span class="detail-value mono">{{ $scholarship->student?->student_code }}</span></div>
                        <div class="detail-row"><span class="detail-label">{{ __('Session') }}</span><span class="detail-value">{{ $scholarship->academicSession?->name }}</span></div>
                        <div class="detail-row"><span class="detail-label">{{ __('Status') }}</span><span class="detail-value">{{ $scholarship->status_label }}</span></div>
                        @if ($scholarship->reason)
                            <div class="detail-row"><span class="detail-label">{{ __('Reason') }}</span><span class="detail-value">{{ $scholarship->reason }}</span></div>
                        @endif
                        @if ($scholarship->description)
                            <div class="detail-row"><span class="detail-label">{{ __('Description') }}</span><span class="detail-value">{{ $scholarship->description }}</span></div>
                        @endif
                        <div class="detail-row"><span class="detail-label">{{ __('Created by') }}</span><span class="detail-value">{{ $scholarship->creator?->name ?? '—' }}</span></div>
                        <div class="detail-row"><span class="detail-label">{{ __('Approved by') }}</span><span class="detail-value">{{ $scholarship->approver?->name ?? '—' }}</span></div>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('fees.scholarships.update', $scholarship) }}">
                @csrf @method('PUT')
                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">{{ __('Edit Scholarship') }}</h3></div></div>
                    <div class="form-section">
                        <div class="form-grid">
                            <x-input name="scholarship_name" :label="__('Name')" :value="$scholarship->scholarship_name" required />
                            <x-select name="scholarship_type" :label="__('Type')" :options="\App\Models\StudentScholarship::TYPES" :selected="$scholarship->scholarship_type" :placeholder="false" required />
                            <x-select name="discount_method" :label="__('Discount Method')" :options="['percent' => __('Percentage (%)'), 'fixed' => __('Fixed Amount')]" :selected="$scholarship->discount_method" :placeholder="false" required />
                            <x-input name="discount_value" :label="__('Discount Value')" type="number" step="0.01" min="0.01" :value="$scholarship->discount_value" required />
                            <x-input name="start_date" :label="__('Start Date')" type="date" :value="$scholarship->start_date->toDateString()" required />
                            <x-input name="end_date" :label="__('End Date')" type="date" :value="$scholarship->end_date->toDateString()" required />
                            <div class="span-2"><x-textarea name="reason" :label="__('Reason')" rows="2">{{ $scholarship->reason }}</x-textarea></div>
                        </div>
                        <div style="display:flex;justify-content:flex-end;margin-top:12px">
                            <x-button type="submit" variant="primary" icon="check">{{ __('Save Changes') }}</x-button>
                        </div>
                    </div>
                </div>
            </form>

            <div class="card">
                <div class="card-header"><div><h3 class="card-title">{{ __('Actions') }}</h3></div></div>
                <div class="form-section stack">
                    @if (in_array($scholarship->status, ['pending', 'draft', 'approved'], true))
                        <form method="POST" action="{{ route('fees.scholarships.approve', $scholarship) }}">
                            @csrf
                            <label class="field" style="cursor:pointer;flex-direction:row;align-items:center;gap:8px">
                                <input type="checkbox" name="apply_to_existing" value="1">
                                <span>{{ __('Also apply to existing unpaid invoices now') }}</span>
                            </label>
                            <x-button type="submit" variant="primary" icon="check" class="btn-block">{{ __('Approve & Activate') }}</x-button>
                        </form>
                        <form method="POST" action="{{ route('fees.scholarships.reject', $scholarship) }}">
                            @csrf
                            <x-button type="submit" variant="ghost" icon="x" class="btn-block">{{ __('Reject') }}</x-button>
                        </form>
                    @endif

                    @if ($scholarship->status === 'active')
                        <form method="POST" action="{{ route('fees.scholarships.suspend', $scholarship) }}">
                            @csrf
                            <x-button type="submit" variant="secondary" icon="pause" class="btn-block">{{ __('Suspend') }}</x-button>
                        </form>
                    @endif

                    @if ($scholarship->status === 'suspended')
                        <form method="POST" action="{{ route('fees.scholarships.reactivate', $scholarship) }}">
                            @csrf
                            <x-button type="submit" variant="primary" icon="play" class="btn-block">{{ __('Reactivate') }}</x-button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('fees.scholarships.renew', $scholarship) }}">
                        @csrf
                        <x-button type="submit" variant="secondary" icon="refresh" class="btn-block">{{ __('Renew') }}</x-button>
                    </form>

                    @if ($scholarship->adjustments->isEmpty())
                        <form method="POST" action="{{ route('fees.scholarships.destroy', $scholarship) }}" onsubmit="return confirm('{{ __('Delete this scholarship?') }}')">
                            @csrf @method('DELETE')
                            <x-button type="submit" variant="ghost" icon="trash" class="btn-block">{{ __('Delete') }}</x-button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
