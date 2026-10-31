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

    <div class="grid-main-side">
        <div class="stack">
            <form method="POST" action="{{ route('fees.collection.store', $student) }}">
                @csrf
                <div class="card">
                    <div class="card-header">
                        <div><h3 class="card-title">Outstanding Fees</h3><p class="card-sub">Enter an amount against one or more rows</p></div>
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
                        <div class="detail-row"><span class="detail-label">Admission no</span><span class="detail-value mono">{{ $student->admission_no }}</span></div>
                        <div class="detail-row"><span class="detail-label">Class</span><span class="detail-value">{{ $enrollment->class?->name }} · {{ $enrollment->section?->name }}</span></div>
                        <div class="detail-row"><span class="detail-label">Guardian</span><span class="detail-value">{{ $student->primaryGuardian()?->name ?? '—' }}</span></div>
                        <div class="detail-row"><span class="detail-label">Phone</span><span class="detail-value">{{ $student->primaryGuardian()?->phone ?? '—' }}</span></div>
                        <div class="detail-row"><span class="detail-label">Total due</span><span class="detail-value {{ $outstanding->sum(fn($a) => $a->balance) > 0 ? 'text-danger' : 'text-success' }}">{{ money($outstanding->sum(fn($a) => $a->balance)) }}</span></div>
                    </div>
                </div>
            </div>

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
@endsection
