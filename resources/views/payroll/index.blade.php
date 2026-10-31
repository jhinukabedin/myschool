@extends('layouts.app')

@section('title', 'Salary Management')

@section('content')
    <x-page-header eyebrow="Payroll" title="Salary Management" :subtitle="$session ? 'Payroll for '.$session->name.' · '.$month : 'No academic session is configured.'">
        <x-slot:actions>
            <x-button :href="route('payroll.report')" variant="secondary" icon="file-text">Salary report</x-button>
            @can('payroll.manage')
                <form method="POST" action="{{ route('payroll.generate') }}" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    @csrf
                    <input class="input" type="month" name="month" value="{{ $month }}" style="width:160px" aria-label="Payroll month">
                    <x-button type="submit" variant="primary" icon="refresh">Generate payroll</x-button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="stat-grid" style="grid-template-columns:repeat(5,minmax(0,1fr))">
        <x-stat-card label="Net payroll" :value="money($summary['net'])" icon="banknote" tone="student" :meta="$month" />
        <x-stat-card label="Paid" :value="money($summary['paid'])" icon="check-circle" tone="collection" meta="Valid installments" />
        <x-stat-card label="Outstanding" :value="money($summary['outstanding'])" icon="clock" tone="expense" meta="Still payable" />
        <x-stat-card label="Processed / partial" :value="number_format($summary['processed'] + $summary['partial'])" icon="file-text" tone="expense" meta="Awaiting full payment" />
        <x-stat-card label="Pending" :value="number_format($summary['pending'])" icon="clock" tone="pending" meta="Generated, not processed" />
    </div>

    <div class="card">
        <div class="card-header">
            <div><h3 class="card-title">Payroll Register</h3><p class="card-sub">{{ $payrolls->total() }} salary row(s) · {{ $month }}</p></div>
            <form method="GET" action="{{ route('payroll.index') }}" class="filter-bar" style="margin:0">
                <div class="field"><input class="input" type="month" name="month" value="{{ $month }}" aria-label="Filter month"></div>
                <div class="field">
                    <select class="select" name="status">
                        <option value="">Any status</option>
                        @foreach (['pending' => 'Pending', 'processed' => 'Processed', 'partially_paid' => 'Partially paid', 'paid' => 'Paid', 'cancelled' => 'Cancelled'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <x-button type="submit" variant="secondary" icon="filter">Filter</x-button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Staff</th><th>Month</th><th class="num">Net</th><th class="num">Paid</th><th class="num">Outstanding</th><th>Status</th><th>Installments</th><th class="actions">Actions</th></tr></thead>
                <tbody>
                    @forelse ($payrolls as $payroll)
                        @php($paid = $payroll->paidAmount())
                        @php($outstanding = $payroll->outstandingAmount())
                        <tr>
                            <td>
                                <div class="cell-title">{{ $payroll->staff?->full_name }}</div>
                                <div class="cell-sub">{{ $payroll->staff?->employee_no }} · {{ $payroll->staff?->department?->name }}</div>
                            </td>
                            <td>{{ $payroll->month }}</td>
                            <td class="num" style="font-weight:600">{{ money($payroll->net_salary) }}</td>
                            <td class="num text-success">{{ money($paid) }}</td>
                            <td class="num {{ $outstanding > 0 ? 'text-danger' : '' }}">{{ money($outstanding) }}</td>
                            <td>
                                <x-badge :color="$payroll->status === 'paid' ? 'success' : ($payroll->status === 'partially_paid' ? 'warning' : ($payroll->status === 'processed' ? 'info' : ($payroll->status === 'cancelled' ? 'danger' : 'muted')))">
                                    {{ ucfirst(str_replace('_', ' ', $payroll->status)) }}
                                </x-badge>
                            </td>
                            <td class="cell-sub">{{ $payroll->completedPayments->count() }} paid · {{ $payroll->payments->where('status', 'reversed')->count() }} reversed</td>
                            <td class="actions">
                                @can('payroll.manage')
                                    @if ($payroll->status === 'pending')
                                        <form method="POST" action="{{ route('payroll.process', $payroll) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-secondary btn-sm" title="Process"><x-icon name="check" :size="13" /> Process</button>
                                        </form>
                                    @elseif (in_array($payroll->status, ['processed', 'partially_paid'], true) && $outstanding > 0)
                                        <button type="button" class="btn btn-success btn-sm" @click="$dispatch('open-modal', 'pay-{{ $payroll->id }}')"><x-icon name="banknote" :size="13" /> Pay</button>
                                    @endif
                                    @if ($paid > 0)
                                        <button type="button" class="btn btn-secondary btn-sm" @click="$dispatch('open-modal', 'reverse-{{ $payroll->id }}')"><x-icon name="refresh" :size="13" /> Reverse</button>
                                    @endif
                                    @if ($paid <= 0 && in_array($payroll->status, ['pending', 'processed'], true))
                                        <button type="button" class="btn btn-secondary btn-sm" @click="$dispatch('open-modal', 'cancel-{{ $payroll->id }}')"><x-icon name="x" :size="13" /> Cancel</button>
                                    @endif
                                @endcan
                                <a href="{{ route('payroll.slip', $payroll) }}" class="btn-icon" title="Salary slip"><x-icon name="receipt" :size="15" /></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="table-empty">No payroll rows exist for this month. Generate payroll to begin.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $payrolls->links() }}
    </div>

    @can('payroll.manage')
        @foreach ($payrolls as $payroll)
            @php($outstanding = $payroll->outstandingAmount())
            @if (in_array($payroll->status, ['processed', 'partially_paid'], true) && $outstanding > 0)
                <x-modal name="pay-{{ $payroll->id }}" :title="'Record salary installment · '.$payroll->staff?->full_name">
                    <form method="POST" action="{{ route('payroll.pay', $payroll) }}">
                        @csrf
                        <div class="stack">
                            <div class="detail-list">
                                <div class="detail-row"><span class="detail-label">Month</span><span class="detail-value">{{ $payroll->month }}</span></div>
                                <div class="detail-row"><span class="detail-label">Net salary</span><span class="detail-value">{{ money($payroll->net_salary) }}</span></div>
                                <div class="detail-row"><span class="detail-label">Already paid</span><span class="detail-value">{{ money($payroll->paidAmount()) }}</span></div>
                                <div class="detail-row"><span class="detail-label">Outstanding</span><span class="detail-value">{{ money($outstanding) }}</span></div>
                            </div>
                            <x-input name="amount" label="Installment amount" type="number" step="0.01" min="0.01" :value="$outstanding" required />
                            <x-select name="payment_method" label="Payment method" :options="['cash' => 'Cash', 'bank' => 'Bank transfer', 'mobile_banking' => 'Mobile banking', 'cheque' => 'Cheque', 'card' => 'Card']" required />
                            <x-input name="reference_no" label="Reference no" placeholder="Optional for cash" />
                            <x-input name="paid_at" label="Paid at" type="datetime-local" :value="now()->format('Y-m-d\TH:i')" required />
                            <x-input name="note" label="Note" placeholder="Optional note" />
                            <x-button type="submit" variant="primary" icon="check-circle" class="btn-block">Record installment</x-button>
                        </div>
                    </form>
                </x-modal>
            @endif
        @endforeach

        @foreach ($payrolls as $payroll)
            @if ($payroll->paidAmount() <= 0 && in_array($payroll->status, ['pending', 'processed'], true))
                <x-modal name="cancel-{{ $payroll->id }}" :title="'Cancel payroll · '.$payroll->staff?->full_name">
                    <form method="POST" action="{{ route('payroll.cancel', $payroll) }}">
                        @csrf
                        <div class="stack">
                            <div class="warning-box">
                                The payroll row is retained for audit and excluded from payable totals. Cancellation is only possible before any salary installment is recorded.
                            </div>
                            <x-textarea name="reason" label="Cancellation reason" rows="2" required placeholder="Explain why this payroll is being cancelled" />
                            <x-button type="submit" variant="secondary" icon="x" class="btn-block">Cancel payroll</x-button>
                        </div>
                    </form>
                </x-modal>
            @endif
        @endforeach

        @foreach ($payrolls as $payroll)
            @php($completed = $payroll->completedPayments)
            @if ($completed->isNotEmpty())
                <x-modal name="reverse-{{ $payroll->id }}" :title="'Reverse salary installment · '.$payroll->staff?->full_name">
                    <div class="stack">
                        <div class="warning-box">
                            The original installment is retained for audit. Reversing reduces the paid balance and can return the payroll to processed, partially paid or fully paid as appropriate.
                        </div>
                        @foreach ($completed as $payment)
                            <form method="POST" action="{{ route('payroll.payments.reverse', $payment) }}" class="stack" style="border:1px solid var(--border);padding:12px;border-radius:10px">
                                @csrf
                                <div class="detail-list">
                                    <div class="detail-row"><span class="detail-label">Amount</span><span class="detail-value">{{ money($payment->amount) }}</span></div>
                                    <div class="detail-row"><span class="detail-label">Method</span><span class="detail-value">{{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</span></div>
                                    <div class="detail-row"><span class="detail-label">Paid on</span><span class="detail-value">{{ $payment->paid_at?->format('d M Y, h:i A') }}</span></div>
                                </div>
                                <x-textarea name="reason" label="Reversal reason" rows="2" required placeholder="Explain why this installment is being reversed" />
                                <x-button type="submit" variant="secondary" icon="refresh" class="btn-block">Reverse this installment</x-button>
                            </form>
                        @endforeach
                    </div>
                </x-modal>
            @endif
        @endforeach
    @endcan
@endsection
