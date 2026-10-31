@extends('layouts.print')

@section('title', 'Receipt '.$payment->receipt_no)

@section('content')
    @php($enrollment = $payment->student?->enrollments->firstWhere('academic_session_id', $payment->academic_session_id))

    <div class="print-hide" style="max-width:760px;margin:0 auto 12px;display:flex;justify-content:space-between;align-items:center;gap:8px">
        <a href="{{ route('fees.collection.show', $payment->student) }}" class="btn btn-secondary btn-sm">Back to collection</a>
        <span class="muted-2" style="font-size:11px">Use your browser print dialog to save as PDF.</span>
    </div>

    <div class="receipt-sheet">
        <div class="receipt-head">
            <div>
                <div class="receipt-school">{{ $schoolSettings['name'] ?? 'EduSphere School' }}</div>
                <div class="receipt-sub">{{ $schoolSettings['address'] ?? '' }}</div>
                <div class="receipt-sub">
                    {{ $schoolSettings['phone'] ?? '' }}{{ ! empty($schoolSettings['email']) ? ' · '.$schoolSettings['email'] : '' }}
                </div>
            </div>
            <div class="receipt-badge">
                <div class="r-title">Fee receipt</div>
                <div class="receipt-no">{{ $payment->receipt_no }}</div>
                <div class="receipt-sub">{{ $payment->paid_at?->format('d M Y, h:i A') }}</div>
            </div>
        </div>

        @if ($payment->status === 'reversed')
            <div style="margin-top:16px;padding:10px 12px;border:1px solid #fecaca;background:#fef2f2;color:#991b1b;border-radius:8px;font-size:11px">
                <strong>Reversed payment.</strong>
                This receipt is retained for audit. It is not counted as collected revenue.
                @foreach ($payment->reversals as $reversal)
                    <div style="margin-top:4px">{{ ucfirst($reversal->type) }} on {{ $reversal->reversed_at?->format('d M Y, h:i A') }} by {{ $reversal->reversedBy?->name ?? 'System' }} — {{ $reversal->reason }}</div>
                @endforeach
            </div>
        @endif

        <div class="receipt-grid">
            <div><div class="receipt-label">Student</div><div class="receipt-value">{{ $payment->student?->full_name }}</div></div>
            <div><div class="receipt-label">Admission number</div><div class="receipt-value">{{ $payment->student?->admission_no }}</div></div>
            <div><div class="receipt-label">Class</div><div class="receipt-value">{{ $enrollment?->class?->name ?? '—' }}{{ $enrollment?->section ? ' · '.$enrollment->section->name : '' }}</div></div>
            <div><div class="receipt-label">Academic session</div><div class="receipt-value">{{ $payment->academicSession?->name }}</div></div>
            <div><div class="receipt-label">Payment method</div><div class="receipt-value">{{ $payment->method_label }}</div></div>
            <div><div class="receipt-label">Reference</div><div class="receipt-value">{{ $payment->reference_no ?? '—' }}</div></div>
        </div>

        <table class="receipt-table">
            <thead><tr><th>Fee head</th><th>Period</th><th style="text-align:right">Amount</th></tr></thead>
            <tbody>
                @foreach ($payment->allocations as $allocation)
                    <tr>
                        <td>{{ $allocation->assignment?->category?->name ?? 'Fee' }}</td>
                        <td>{{ $allocation->assignment?->period ?? 'One-time' }}</td>
                        <td style="text-align:right">{{ money($allocation->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="receipt-total">Total paid: {{ money($payment->amount) }}</div>

        <div style="display:flex;justify-content:space-between;gap:16px;margin-top:16px;font-size:10.5px;color:#6b7280">
            <div>Received by: <strong>{{ $payment->receivedBy?->name ?? 'System' }}</strong></div>
            <div>Status: <strong style="color:{{ $payment->status === 'completed' ? '#047857' : '#b91c1c' }}">{{ strtoupper($payment->status) }}</strong></div>
        </div>

        @if ($payment->note)
            <div style="margin-top:12px;font-size:10.5px;color:#4b5563"><strong>Note:</strong> {{ $payment->note }}</div>
        @endif

        <div class="receipt-footer">
            <div>{{ $schoolSettings['currency'] ?? '৳' }} {{ number_format((float) $payment->amount, 2) }} received with thanks. This is a computer-generated receipt.</div>
            <div class="signature">
                <div class="signature-line">Authorised signature</div>
            </div>
        </div>
    </div>

    @can('fees.reverse')
        @if ($payment->status === 'completed')
            <div class="print-hide" style="max-width:760px;margin:14px auto 0">
                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">Reverse this payment</h3><p class="card-sub">The receipt stays in the ledger; the allocation is released and the balance recalculated.</p></div></div>
                    <form method="POST" action="{{ route('payments.reverse', $payment) }}" onsubmit="return confirm('Reverse this payment? This cannot be undone and is recorded in the audit log.')">
                        @csrf
                        <div class="form-section">
                            <div class="form-grid">
                                <x-select name="type" label="Reversal type" :options="['cancellation' => 'Cancellation', 'refund' => 'Refund']" required />
                                <x-input name="reason" label="Reason" required placeholder="Why is this payment being reversed?" />
                            </div>
                        </div>
                        <div class="form-section" style="display:flex;justify-content:flex-end">
                            <x-button type="submit" variant="danger" icon="rotate-ccw">Reverse payment</x-button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endcan
@endsection
