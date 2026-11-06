@extends('layouts.app')

@section('title', 'Collect Fees')

@php($outstanding = $assignments->whereIn('status', ['pending', 'partial']))

@section('content')
    <x-page-header eyebrow="Fees" :title="'Collect · '.$student->full_name" :subtitle="($enrollment->class?->name ?? '').' · Section '.$enrollment->section?->name.' · '.$session->name">
        <x-slot:actions>
            <x-button :href="route('students.show', $student)" variant="secondary" icon="user">Student profile</x-button>
            <x-button :href="route('fees.collection.index')" variant="ghost" icon="arrow-left">Collection ledger</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($assignments->isEmpty())
        <div class="alert alert-warning"><span class="alert-icon"><x-icon name="alert-triangle" :size="17" /></span><div>No fee rows are assigned for this student. Assign a fee structure below first.</div></div>
    @endif

    @if ($hiddenFutureCount > 0 || $includeFuture)
        <div class="alert alert-info" style="display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap">
            <div>
                @if ($includeFuture)
                    <strong>{{ __('Advance payment mode') }}</strong> — {{ __('future invoices are included and can be paid ahead of time.') }}
                @else
                    <strong>{{ __('Billing window') }}: {{ $windowLabel }}</strong>
                    — {{ $hiddenFutureCount }} {{ __('future invoice(s)') }} ({{ money($hiddenFutureDue) }}) {{ __('are not due yet and are hidden from this total.') }}
                @endif
            </div>
            @can('fees.collect')
                @if ($includeFuture)
                    <x-button :href="route('fees.collection.show', $student)" variant="ghost" icon="arrow-left">{{ __('Show only current dues') }}</x-button>
                @else
                    <x-button :href="route('fees.collection.show', ['student' => $student, 'include_future' => 1])" variant="secondary" icon="calendar">{{ __('Add future months / advance payment') }}</x-button>
                @endif
            @endcan
        </div>
    @endif

    <div class="grid-main-side">
        <div class="stack">
            <form method="POST" action="{{ route('fees.collection.store', $student) }}">
                @csrf
                <div class="card">
                    <div class="card-header">
                        <div><h3 class="card-title">{{ __('Outstanding Fees') }}</h3><p class="card-sub">{{ __('Enter an amount against one or more rows') }} · {{ __('Scholarship is set per fee') }} · {{ __('Window') }}: {{ $windowLabel }}</p></div>
                        <span class="badge badge-violet">{{ $outstanding->count() }} open</span>
                    </div>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead><tr><th>Fee</th><th>Period</th><th>Due date</th><th class="num">Net</th><th class="num">Paid</th><th class="num">Balance</th><th style="width:150px">Collect</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($assignments as $assignment)
                                    <tr>
                                        <td>
                                            <div class="cell-title">{{ $assignment->category?->name }}</div>
                                            @if ($assignment->waiver_amount > 0)<div class="cell-sub">Waiver {{ money($assignment->waiver_amount) }}</div>@endif
                                            @if ((float) $assignment->scholarship_amount > 0)
                                                <div class="cell-sub" style="color:var(--ok, #0a7d55)">
                                                    {{ __('Scholarship') }} {{ $assignment->scholarship_label }} · {{ money($assignment->scholarship_amount) }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="muted">{{ $assignment->period ?? 'One-time' }}</td>
                                        <td>{{ $assignment->due_date?->format('d M Y') }}</td>
                                        <td class="num">{{ money($assignment->net_amount) }}</td>
                                        <td class="num text-success">{{ money($assignment->paid_amount) }}</td>
                                        <td class="num {{ $assignment->balance > 0 ? 'text-danger' : 'text-success' }}">{{ money($assignment->balance) }}</td>
                                        <td>
                                            @if (in_array($assignment->status, ['pending', 'partial'], true))
                                                <input class="input" type="number" step="0.01" min="0" max="{{ $assignment->balance }}" name="allocations[{{ $assignment->id }}]" value="{{ old('allocations.'.$assignment->id) }}" placeholder="{{ number_format($assignment->balance, 2, '.', '') }}">
                                            @else
                                                <span class="badge badge-{{ $assignment->status === 'paid' ? 'success' : 'info' }}">{{ ucfirst($assignment->status) }}</span>
                                            @endif
                                        </td>
                                        <td class="actions">
                                            @if ($assignment->balance > 0)
                                                @can('fees.waive')
                                                    <button type="button" class="btn-icon" title="Approve waiver" @click="$dispatch('open-modal', 'waiver-{{ $assignment->id }}')"><x-icon name="percent" :size="15" /></button>
                                                    <button type="button" class="btn-icon" title="Set scholarship" @click="$dispatch('open-modal', 'scholarship-{{ $assignment->id }}')"><x-icon name="graduation-cap" :size="15" /></button>
                                                @endcan
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="table-empty">No fee rows available.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($outstanding->isNotEmpty())
                    <div class="card">
                        <div class="card-header"><div><h3 class="card-title">Payment Details</h3><p class="card-sub">A receipt is generated automatically</p></div></div>
                        <div class="form-section">
                            <div class="form-grid">
                                <x-select name="payment_method" label="Payment method" :options="['cash' => 'Cash', 'bank' => 'Bank transfer', 'mobile_banking' => 'Mobile banking', 'cheque' => 'Cheque', 'card' => 'Card']" required />
                                <x-input name="reference_no" label="Reference / transaction no" placeholder="Optional for cash" />
                                <x-input name="paid_at" label="Paid at" type="datetime-local" :value="now()->format('Y-m-d\TH:i')" required />
                                <x-input name="note" label="Note" />
                            </div>
                            <p class="field-hint" style="margin-top:10px">Scholarship is granted per fee row — use the award icon on any outstanding fee to set a percentage or fixed concession before collecting.</p>
                        </div>
                        <div class="form-section" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                            <span class="muted">Blank rows are ignored. Amounts cannot exceed each balance.</span>
                            @can('fees.collect')
                                <x-button type="submit" variant="primary" icon="receipt">Collect & issue receipt</x-button>
                            @endcan
                        </div>
                    </div>
                @endif
            </form>

            <div class="card">
                <div class="card-header"><div><h3 class="card-title">Payment History</h3><p class="card-sub">All receipts for this session</p></div></div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Receipt</th><th>Date</th><th>Method</th><th class="num">Amount</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($payments as $payment)
                                <tr>
                                    <td class="mono" style="font-size:11px">{{ $payment->receipt_no }}</td>
                                    <td>{{ $payment->paid_at?->format('d M Y, h:i A') }}</td>
                                    <td>{{ $payment->method_label }}</td>
                                    <td class="num">{{ money($payment->amount) }}</td>
                                    <td><x-badge :color="$payment->status === 'completed' ? 'success' : 'danger'">{{ ucfirst($payment->status) }}</x-badge></td>
                                    <td class="actions"><a href="{{ route('payments.receipt', $payment) }}" class="btn-icon"><x-icon name="receipt" :size="15" /></a></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="table-empty">No payments in this session.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <div class="card-header"><div><h3 class="card-title">Student Summary</h3></div></div>
                <div class="card-body">
                    <div class="detail-list">
                        <div class="detail-row"><span class="detail-label">Student ID</span><span class="detail-value mono">{{ $student->student_code }}</span></div>
                        <div class="detail-row"><span class="detail-label">Class</span><span class="detail-value">{{ $enrollment->class?->name }} · {{ $enrollment->section?->name }}</span></div>
                        <div class="detail-row"><span class="detail-label">Guardian</span><span class="detail-value">{{ $student->primaryGuardian()?->name ?? '—' }}</span></div>
                        <div class="detail-row"><span class="detail-label">Phone</span><span class="detail-value">{{ $student->primaryGuardian()?->phone ?? '—' }}</span></div>
                        <div class="detail-row"><span class="detail-label">Total due</span><span class="detail-value {{ $outstanding->sum(fn($a) => $a->balance) > 0 ? 'text-danger' : 'text-success' }}">{{ money($outstanding->sum(fn($a) => $a->balance)) }}</span></div>
                    </div>
                </div>
            </div>

            @if (! empty($scholarships) && $scholarships->isNotEmpty())
                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">{{ __('Scholarships') }}</h3><p class="card-sub">{{ __('Active for this session') }}</p></div></div>
                    <div class="card-body">
                        <div class="stack" style="gap:8px">
                            @foreach ($scholarships as $scholarship)
                                <div class="due-row">
                                    <div class="due-info">
                                        <span class="table-avatar"><x-icon name="graduation-cap" :size="15" /></span>
                                        <div>
                                            <div class="cell-title">{{ $scholarship->scholarship_name }} · {{ $scholarship->type_label }}</div>
                                            <div class="cell-sub">
                                                {{ $scholarship->discount_method === 'percent' ? rtrim(rtrim(number_format((float) $scholarship->discount_value, 2, '.', ''), '0'), '.').'%' : money($scholarship->discount_value) }} {{ __('on') }} {{ $scholarship->applies_to === 'all' ? __('all fees') : $scholarship->items->pluck('category.name')->filter()->join(', ') }}
                                                · {{ $scholarship->start_date->format('d M Y') }} – {{ $scholarship->end_date->format('d M Y') }}
                                            </div>
                                        </div>
                                    </div>
                                    <span class="badge badge-{{ $scholarship->status === 'active' ? 'success' : 'muted' }}">{{ $scholarship->status_label }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            @can('fees.manage')
                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">Assign Fee Structure</h3><p class="card-sub">Idempotent: existing rows are skipped</p></div></div>
                    <form method="POST" action="{{ route('fees.assign', $student) }}">
                        @csrf
                        <div class="form-section">
                            <x-select name="fee_structure_id" label="Structure" :options="$structures->pluck('name', 'id')" required />
                            <p class="field-hint">Optional items are only assigned when selected on the admission form. Non-optional items are added now.</p>
                        </div>
                        <div class="form-section" style="display:flex;justify-content:flex-end">
                            <x-button type="submit" variant="secondary" icon="plus">Assign structure</x-button>
                        </div>
                    </form>
                </div>
            @endcan

            <div class="card">
                <div class="card-header"><div><h3 class="card-title">Financial Controls</h3></div></div>
                <div class="card-body">
                    <div class="info-box">Receipt numbers are generated atomically. Reversing a payment keeps both the original receipt and the reversal record.</div>
                </div>
            </div>
        </div>
    </div>

    @can('fees.waive')
        @foreach ($assignments as $assignment)
            @if ($assignment->balance > 0)
                <x-modal name="waiver-{{ $assignment->id }}" :title="'Approve waiver · '.$assignment->category?->name">
                    <form method="POST" action="{{ route('fees.assignments.waiver', $assignment) }}">
                        @csrf
                        <div class="stack">
                            <div class="detail-list">
                                <div class="detail-row"><span class="detail-label">Period</span><span class="detail-value">{{ $assignment->period ?? 'One-time' }}</span></div>
                                <div class="detail-row"><span class="detail-label">Outstanding</span><span class="detail-value">{{ money($assignment->balance) }}</span></div>
                            </div>
                            <x-input name="waiver_amount" label="Waiver amount" type="number" step="0.01" min="0.01" max="{{ $assignment->balance }}" required />
                            <x-input name="reason" label="Reason" required placeholder="Approved by principal, hardship..." />
                            <x-button type="submit" variant="primary" icon="percent" class="btn-block">Approve waiver</x-button>
                        </div>
                    </form>
                </x-modal>
            @endif
        @endforeach
    @endcan
    @can('fees.waive')
        @foreach ($assignments as $assignment)
            @if ($assignment->balance > 0)
                <x-modal name="scholarship-{{ $assignment->id }}" :title="__('Scholarship').' · '.$assignment->category?->name">
                    <form method="POST" action="{{ route('fees.assignments.scholarship', $assignment) }}">
                        @csrf
                        <div class="stack">
                            <div class="detail-list">
                                <div class="detail-row"><span class="detail-label">{{ __('Period') }}</span><span class="detail-value">{{ $assignment->period ?? __('One-time') }}</span></div>
                                <div class="detail-row"><span class="detail-label">{{ __('Fee amount') }}</span><span class="detail-value">{{ money($assignment->amount) }}</span></div>
                                <div class="detail-row"><span class="detail-label">{{ __('Current scholarship') }}</span><span class="detail-value">{{ $assignment->scholarship_label }}</span></div>
                            </div>
                            <p class="field-hint" style="margin:0">{{ __('Choose how the concession is calculated: a percentage of this fee, or a fixed amount. It reduces the payable balance immediately.') }}</p>
                            <x-select name="scholarship_type" :label="__('Scholarship type')" :options="['percent' => __('Percentage (%)'), 'fixed' => __('Fixed amount'), 'none' => __('No scholarship')]" :selected="$assignment->scholarship_type ?? 'percent'" :placeholder="false" required />
                            <x-input name="scholarship_value" :label="__('Value')" type="number" step="0.01" min="0" :max="$assignment->scholarship_type === 'percent' ? 100 : $assignment->net_amount" :value="$assignment->scholarship_value" />
                            <x-button type="submit" variant="primary" icon="graduation-cap" class="btn-block">{{ __('Apply scholarship') }}</x-button>
                        </div>
                    </form>
                </x-modal>
            @endif
        @endforeach
    @endcan
@endsection
