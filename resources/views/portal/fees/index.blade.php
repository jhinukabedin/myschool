@extends('layouts.portal')

@section('title', 'Fees & Payments')
@section('banner-subtitle', 'নির্ধারিত ফি, পরিশোধ ও বকেয়ার হিসাব')

@section('content')
    @if ($portalRole === 'parent')
        <div class="page-head">
            <div>
                <p class="eyebrow">Fee ledger</p>
                <h1>{{ $student->full_name }}'s Fees</h1>
                <p>Assigned fees, verified manual payments, discounts and outstanding balances for {{ $session?->name ?? 'the current session' }}.</p>
            </div>
            @if (isset($children) && $children->count() > 1)
                <div class="selector-form">
                    <div class="field">
                        <label for="child-switch">Child</label>
                        <select id="child-switch" onchange="if(this.value) window.location.href=this.value">
                            @foreach ($children as $childOption)
                                <option value="{{ route('parent.fees.index', $childOption) }}" @selected($childOption->id === $student->id)>{{ $childOption->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="metric-grid">
        <div class="card stat"><div class="stat-label">Net assigned</div><div class="stat-value">{{ money($fees['total_charged']) }}</div><div class="stat-meta">After discounts and waivers</div></div>
        <div class="card stat"><div class="stat-label">Verified paid</div><div class="stat-value" style="color:var(--success)">{{ money($fees['total_paid']) }}</div><div class="stat-meta">Completed payments only</div></div>
        <div class="card stat"><div class="stat-label">Balance</div><div class="stat-value" style="color:{{ $fees['balance'] > 0 ? 'var(--danger)' : 'var(--success)' }}">{{ money($fees['balance']) }}</div><div class="stat-meta">Across all assignments</div></div>
        <div class="card stat"><div class="stat-label">Overdue</div><div class="stat-value">{{ money($fees['overdue']) }}</div><div class="stat-meta">Past the due date</div></div>
    </div>

    <div class="hero-note" style="margin-bottom:18px">
        Online payment is not enabled on this portal. A payment is only counted after the school office records and verifies it. Reversed payments are excluded from the balance.
    </div>

    <section class="card">
        <div class="card-head"><div><h2>Fee assignments</h2><p>Every fee head applied to the student record</p></div></div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Fee head</th><th>Period</th><th>Due date</th><th class="num">Gross</th><th class="num">Discount / waiver</th><th class="num">Net</th><th class="num">Paid</th><th class="num">Balance</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($fees['assignments'] as $assignment)
                        <tr>
                            <td class="title-cell"><strong>{{ $assignment->category?->name ?? 'Fee' }}</strong><span>{{ $assignment->note }}</span></td>
                            <td class="mono">{{ $assignment->period ?? 'One-time' }}</td>
                            <td class="mono">{{ $assignment->due_date?->format('d M Y') ?? '—' }}</td>
                            <td class="num mono">{{ money($assignment->amount) }}</td>
                            <td class="num mono">{{ money((float) $assignment->discount_amount + (float) $assignment->waiver_amount) }}</td>
                            <td class="num mono">{{ money($assignment->net_amount) }}</td>
                            <td class="num mono">{{ money($assignment->paid_amount) }}</td>
                            <td class="num mono">{{ money($assignment->balance) }}</td>
                            <td>
                                @if ($assignment->balance <= 0)
                                    <span class="badge badge-success">Paid</span>
                                @elseif ($assignment->due_date && $assignment->due_date->isPast())
                                    <span class="badge badge-danger">Overdue</span>
                                @else
                                    <span class="badge badge-warning">Due</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty">No fees have been assigned for this session.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card" style="margin-top:18px">
        <div class="card-head"><div><h2>Payment history</h2><p>Receipts issued by the school. Reversed payments remain visible for audit.</p></div></div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Receipt</th><th>Paid at</th><th>Method</th><th class="num">Amount</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($fees['payments'] as $payment)
                        @php
                            $receiptUrl = $portalRole === 'parent'
                                ? route($receiptRoute, [$student, $payment])
                                : route($receiptRoute, $payment);
                        @endphp
                        <tr>
                            <td class="mono">{{ $payment->receipt_no }}</td>
                            <td class="mono">{{ $payment->paid_at?->format('d M Y, h:i A') }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $payment->payment_method ?? '—')) }}{{ $payment->reference_no ? ' · '.$payment->reference_no : '' }}</td>
                            <td class="num mono">{{ money($payment->amount) }}</td>
                            <td>
                                <span class="badge {{ $payment->status === 'completed' ? 'badge-success' : 'badge-danger' }}">{{ ucfirst($payment->status) }}</span>
                                @if ($payment->status === 'reversed' && $payment->reversals->isNotEmpty())
                                    <div class="small muted">{{ $payment->reversals->first()->reason }}</div>
                                @endif
                            </td>
                            <td><a class="btn btn-secondary btn-sm" href="{{ $receiptUrl }}">Receipt</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">No payments have been recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
