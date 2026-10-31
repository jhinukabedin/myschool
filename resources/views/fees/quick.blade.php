@extends('layouts.app')

@section('title', 'Quick Fee Collect')

@section('content')
    <x-page-header eyebrow="Fees" title="Quick Fee Collect" :subtitle="$session ? 'Collect a lump sum and let the system allocate it to the oldest outstanding fees for '.$session->name.'.' : 'No academic session is configured.'">
        <x-slot:actions>
            <x-button :href="route('fees.collection.index')" variant="secondary" icon="list">Collection ledger</x-button>
        </x-slot:actions>
    </x-page-header>

    @if (! $session)
        <div class="alert alert-warning"><span class="alert-icon"><x-icon name="alert-triangle" :size="17" /></span><div>Create and activate an academic session before collecting fees.</div></div>
    @endif

    <div class="stat-grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
        <x-stat-card label="Page outstanding" :value="money($dues->sum())" icon="wallet" tone="pending" :meta="$enrollments->total().' active students'" />
        <x-stat-card label="Recent receipts" :value="$recent->count()" icon="receipt" tone="collection" meta="Last 8 completed payments" />
        <x-stat-card label="Allocation rule" value="Oldest first" icon="clock" tone="student" meta="Automatic against unpaid dues" />
    </div>

    <div class="grid-main-side">
        <div class="card">
            <div class="card-header">
                <div><h3 class="card-title">Select a student</h3><p class="card-sub">Search by name, admission number or guardian phone</p></div>
                <form method="GET" class="filter-bar" style="margin:0">
                    <div class="field grow" style="min-width:190px">
                        <input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="Search students">
                    </div>
                    <x-button type="submit" variant="secondary" icon="search">Search</x-button>
                </form>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Student</th><th>Class</th><th>Guardian</th><th class="num">Outstanding</th><th class="actions">Quick collect</th></tr></thead>
                    <tbody>
                        @forelse ($enrollments as $enrollment)
                            @php($due = (float) ($dues[$enrollment->student_id] ?? 0))
                            <tr>
                                <td>
                                    <div class="cell-main">
                                        <span class="table-avatar">{{ Str::substr($enrollment->student?->full_name ?? '?', 0, 1) }}</span>
                                        <div>
                                            <div class="cell-title">{{ $enrollment->student?->full_name }}</div>
                                            <div class="cell-sub">{{ $enrollment->student?->admission_no }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>{{ $enrollment->class?->name }}</div>
                                    <div class="cell-sub">Section {{ $enrollment->section?->name }} &middot; Roll {{ $enrollment->roll_no }}</div>
                                </td>
                                <td>
                                    <div>{{ $enrollment->student?->primaryGuardian()?->name ?? '—' }}</div>
                                    <div class="cell-sub">{{ $enrollment->student?->primaryGuardian()?->phone }}</div>
                                </td>
                                <td class="num {{ $due > 0 ? 'text-danger' : 'text-success' }}" style="font-weight:600">{{ money($due) }}</td>
                                <td class="actions">
                                    @if ($due > 0)
                                        <button type="button" class="btn btn-secondary btn-sm" @click="$dispatch('open-modal', 'quick-{{ $enrollment->student_id }}')">
                                            <x-icon name="zap" :size="13" /> Collect
                                        </button>
                                    @else
                                        <x-badge color="success">Up to date</x-badge>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="table-empty">No active students match your search.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $enrollments->links() }}
        </div>

        <div class="stack">
            <div class="card">
                <div class="card-header"><div><h3 class="card-title">Recent Payments</h3><p class="card-sub">Latest completed receipts</p></div></div>
                <div class="card-body">
                    @forelse ($recent as $payment)
                        <div class="due-row">
                            <div class="due-info">
                                <div class="cell-title">{{ $payment->student?->full_name }}</div>
                                <div class="cell-sub">{{ $payment->receipt_no }} &middot; {{ $payment->paid_at?->format('d M, h:i A') }}</div>
                            </div>
                            <div class="due-amount num text-success" style="font-weight:600">{{ money($payment->amount) }}</div>
                        </div>
                    @empty
                        <x-empty icon="receipt" title="No receipts yet" message="Completed payments appear here." />
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-header"><div><h3 class="card-title">How quick collect works</h3></div></div>
                <div class="card-body">
                    <div class="info-box">The amount is applied to unpaid fee rows using the configured allocation order (oldest dues first by default). Allocation stops when the entered amount is used or all dues are cleared. Overpayments are rejected rather than stored as credit.</div>
                </div>
            </div>
        </div>
    </div>

    @foreach ($enrollments as $enrollment)
        @php($due = (float) ($dues[$enrollment->student_id] ?? 0))
        @if ($due > 0)
            <x-modal name="quick-{{ $enrollment->student_id }}" :title="'Quick collect — '.$enrollment->student?->full_name">
                <form method="POST" action="{{ route('fees.quick.store') }}">
                    @csrf
                    <input type="hidden" name="student_id" value="{{ $enrollment->student_id }}">
                    <div class="stack">
                        <div class="detail-list">
                            <div class="detail-row"><span class="detail-label">Class</span><span class="detail-value">{{ $enrollment->class?->name }} &middot; {{ $enrollment->section?->name }}</span></div>
                            <div class="detail-row"><span class="detail-label">Outstanding</span><span class="detail-value text-danger">{{ money($due) }}</span></div>
                        </div>
                        <div class="form-grid">
                            <x-input name="amount" label="Amount" type="number" step="0.01" min="0.01" :max="$due" :value="number_format($due, 2, '.', '')" required />
                            <x-input name="paid_at" label="Paid at" type="datetime-local" :value="now()->format('Y-m-d\TH:i')" required />
                            <x-select name="payment_method" label="Payment method" :options="['cash' => 'Cash', 'bank' => 'Bank transfer', 'mobile_banking' => 'Mobile banking', 'cheque' => 'Cheque', 'card' => 'Card']" required />
                            <x-input name="reference_no" label="Reference no." />
                            <div class="span-2"><x-input name="note" label="Note" /></div>
                        </div>
                        <x-button type="submit" variant="primary" icon="receipt" class="btn-block">Collect and issue receipt</x-button>
                    </div>
                </form>
            </x-modal>
        @endif
    @endforeach
@endsection
