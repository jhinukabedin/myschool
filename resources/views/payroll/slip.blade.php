@extends('layouts.print')

@section('title', 'Salary Slip '.$payroll->month)

@section('content')
    @php
        $allowances = $payroll->items->where('type', 'allowance');
        $deductions = $payroll->items->where('type', 'deduction');
        $method = $payroll->payment
            ? ucwords(str_replace('_', ' ', $payroll->payment->payment_method))
            : null;
        $latestPayment = $payroll->payments->sortByDesc('id')->first();
        $reversal = $latestPayment && $latestPayment->isReversed()
            ? $latestPayment->reversals->sortByDesc('reversed_at')->first()
            : null;
    @endphp

    <div class="print-hide" style="max-width:760px;margin:0 auto 12px;display:flex;justify-content:space-between;align-items:center;gap:8px">
        <a href="{{ route('payroll.index', ['month' => $payroll->month]) }}" class="btn btn-secondary btn-sm">Back to payroll</a>
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
                <div class="r-title">Salary slip</div>
                <div class="receipt-no">{{ $payroll->month }}</div>
                <div class="receipt-sub">{{ $payroll->academicSession?->name }}</div>
                <div class="receipt-sub">Status: {{ strtoupper($payroll->status) }}</div>
            </div>
        </div>

        <div class="receipt-grid">
            <div><div class="receipt-label">Employee</div><div class="receipt-value">{{ $payroll->staff?->full_name }}</div></div>
            <div><div class="receipt-label">Employee number</div><div class="receipt-value">{{ $payroll->staff?->employee_no }}</div></div>
            <div><div class="receipt-label">Department</div><div class="receipt-value">{{ $payroll->staff?->department?->name ?? '—' }}</div></div>
            <div><div class="receipt-label">Designation</div><div class="receipt-value">{{ $payroll->staff?->designation?->name ?? '—' }}</div></div>
            <div><div class="receipt-label">Employment type</div><div class="receipt-value">{{ $payroll->staff ? ucwords(str_replace('_', ' ', $payroll->staff->employment_type)) : '—' }}</div></div>
            <div><div class="receipt-label">Processed by</div><div class="receipt-value">{{ $payroll->processedBy?->name ?? '—' }}{{ $payroll->processed_at ? ' · '.$payroll->processed_at->format('d M Y') : '' }}</div></div>
        </div>

        <table class="receipt-table">
            <thead>
                <tr>
                    <th style="width:50%">Earnings</th>
                    <th style="text-align:right">Amount</th>
                    <th style="width:50%">Deductions</th>
                    <th style="text-align:right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @php($rowCount = max($allowances->count(), $deductions->count(), 1))
                @for ($i = 0; $i < $rowCount; $i++)
                    @php($earning = $allowances->values()->get($i))
                    @php($deduction = $deductions->values()->get($i))
                    <tr>
                        <td>
                            @if ($i === 0)
                                <strong>Basic salary</strong>
                            @elseif ($earning)
                                {{ $earning->name }}
                            @else
                                &nbsp;
                            @endif
                        </td>
                        <td style="text-align:right">
                            @if ($i === 0)
                                {{ money($payroll->basic_salary) }}
                            @elseif ($earning)
                                {{ money($earning->amount) }}
                            @else
                                &nbsp;
                            @endif
                        </td>
                        <td>{{ $deduction?->name ?? '' }}</td>
                        <td style="text-align:right">{{ $deduction ? money($deduction->amount) : '' }}</td>
                    </tr>
                @endfor
            </tbody>
        </table>

        <div class="receipt-grid" style="margin-top:14px">
            <div><div class="receipt-label">Gross earnings</div><div class="receipt-value">{{ money((float) $payroll->basic_salary + (float) $payroll->total_allowances) }}</div></div>
            <div><div class="receipt-label">Total allowances</div><div class="receipt-value">{{ money($payroll->total_allowances) }}</div></div>
            <div><div class="receipt-label">Total deductions</div><div class="receipt-value">{{ money($payroll->total_deductions) }}</div></div>
            <div><div class="receipt-label">Net salary</div><div class="receipt-value">{{ money($payroll->net_salary) }}</div></div>
        </div>

        <div class="receipt-total">Net payable: {{ money($payroll->net_salary) }}</div>

        @php($completedPayments = $payroll->payments->where('status', 'completed')->sortBy('paid_at'))
        @php($reversedPayments = $payroll->payments->where('status', 'reversed')->sortByDesc('reversed_at'))
        @php($paidAmount = round((float) $completedPayments->sum('amount'), 2))
        @php($outstanding = round(max(0, (float) $payroll->net_salary - $paidAmount), 2))

        <div class="receipt-grid" style="margin-top:14px">
            <div><div class="receipt-label">Total paid</div><div class="receipt-value">{{ money($paidAmount) }}</div></div>
            <div><div class="receipt-label">Outstanding</div><div class="receipt-value">{{ money($outstanding) }}</div></div>
            <div><div class="receipt-label">Payroll status</div><div class="receipt-value">{{ ucfirst(str_replace('_', ' ', $payroll->status)) }}</div></div>
        </div>

        @if ($completedPayments->isNotEmpty() || $reversedPayments->isNotEmpty())
            <table class="receipt-table" style="margin-top:14px">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th style="text-align:right">Amount</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($completedPayments as $payment)
                        <tr>
                            <td>{{ $payment->paid_at?->format('d M Y, h:i A') }}</td>
                            <td style="text-align:right">{{ money($payment->amount) }}</td>
                            <td>{{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}</td>
                            <td>{{ $payment->reference_no ?: '—' }}</td>
                            <td>Completed</td>
                        </tr>
                    @endforeach
                    @foreach ($reversedPayments as $payment)
                        @php($reversal = $payment->reversals->sortByDesc('reversed_at')->first())
                        <tr>
                            <td>{{ $payment->paid_at?->format('d M Y, h:i A') }}</td>
                            <td style="text-align:right;text-decoration:line-through">{{ money($payment->amount) }}</td>
                            <td>{{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}</td>
                            <td>{{ $payment->reference_no ?: '—' }}</td>
                            <td>Reversed {{ $reversal?->reversed_at?->format('d M Y') ? '('.$reversal->reversed_at->format('d M Y').')' : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($reversedPayments->isNotEmpty())
            @php($lastReversal = $reversedPayments->first())
            @php($lastReason = $lastReversal->reversals->sortByDesc('reversed_at')->first())
            <div class="warning-box" style="margin-top:14px">
                <strong>Salary installment reversed.</strong>
                {{ money($lastReversal->amount) }} was reversed on
                {{ $lastReason?->reversed_at?->format('d M Y, h:i A') ?? $lastReversal->reversed_at?->format('d M Y, h:i A') }}.
                @if ($lastReason) Reason: {{ $lastReason->reason }} (by {{ $lastReason->reversedBy?->name ?? 'System' }}). @endif
                The original installment is retained for audit and excluded from the paid balance.
            </div>
        @elseif ($completedPayments->isEmpty())
            @if ($payroll->status === 'processed')
                <div class="warning-box" style="margin-top:14px">
                    Payroll has been processed but no salary installment has been recorded yet.
                </div>
            @endif
        @endif

        <div class="receipt-footer">
            <div>This is a computer-generated salary slip and does not require a signature to be valid.</div>
            <div class="signature">
                <div class="signature-line">Authorised signature</div>
            </div>
        </div>
    </div>
@endsection
