@extends('layouts.app')

@section('title', 'Fee Deletion Log')

@section('content')
    <x-page-header eyebrow="Fees" title="Fee Deletion Log" subtitle="Every cancelled or refunded receipt is retained here. Original payments are never hard-deleted.">
        <x-slot:actions>
            <x-button :href="route('fees.collection.index')" variant="secondary" icon="arrow-left">Collection ledger</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="stat-grid" style="grid-template-columns:repeat(2,minmax(0,1fr))">
        <x-stat-card label="Reversals recorded" :value="number_format($totals['count'])" icon="rotate-ccw" tone="pending" meta="Lifetime audit records" />
        <x-stat-card label="Value reversed" :value="money($totals['amount'])" icon="receipt" tone="expense" meta="Original receipt value" />
    </div>

    <div class="card">
        <div class="card-header">
            <div><h3 class="card-title">Reversal history</h3><p class="card-sub">A reversal releases fee allocations but keeps the original receipt and full audit trail.</p></div>
            <form method="GET" class="filter-bar" style="margin:0">
                <div class="field">
                    <select class="select" name="type">
                        <option value="">All types</option>
                        <option value="cancellation" @selected(request('type') === 'cancellation')>Cancellation</option>
                        <option value="refund" @selected(request('type') === 'refund')>Refund</option>
                    </select>
                </div>
                <x-button type="submit" variant="secondary" icon="filter">Filter</x-button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Receipt</th><th>Student</th><th>Type</th><th class="num">Amount</th><th>Reason</th><th>Reversed by</th><th>When</th><th></th></tr></thead>
                <tbody>
                    @forelse ($reversals as $reversal)
                        <tr>
                            <td class="mono" style="font-size:11px">{{ $reversal->payment?->receipt_no }}</td>
                            <td>
                                <div class="cell-title">{{ $reversal->payment?->student?->full_name }}</div>
                                <div class="cell-sub">Student ID: {{ $reversal->payment?->student?->student_code }}</div>
                            </td>
                            <td><x-badge :color="$reversal->type === 'refund' ? 'info' : 'danger'">{{ ucfirst($reversal->type) }}</x-badge></td>
                            <td class="num">{{ money($reversal->amount) }}</td>
                            <td style="max-width:300px;white-space:normal">{{ $reversal->reason }}</td>
                            <td>{{ $reversal->reversedBy?->name ?? 'System' }}</td>
                            <td class="muted">{{ $reversal->reversed_at?->format('d M Y, h:i A') }}</td>
                            <td class="actions">
                                @if ($reversal->payment)
                                    <a href="{{ route('payments.receipt', $reversal->payment) }}" class="btn-icon" title="View original receipt"><x-icon name="receipt" :size="15" /></a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="table-empty">No payment reversals have been recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $reversals->links() }}
    </div>
@endsection
