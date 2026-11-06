@extends('layouts.app')

@section('title', __('Quick Fee Collect'))

@section('content')
    <x-page-header eyebrow="{{ __('Fees') }}" title="{{ __('Quick Fee Collect') }}" :subtitle="$session ? __('Collect a lump sum and let the system allocate it to the oldest outstanding fees for :session.', ['session' => $session->name]) : __('No academic session is configured.')">
        <x-slot:actions>
            <x-button :href="route('fees.collection.index')" variant="secondary" icon="list">{{ __('Collection ledger') }}</x-button>
        </x-slot:actions>
    </x-page-header>

    @if (! $session)
        <div class="alert alert-warning"><span class="alert-icon"><x-icon name="alert-triangle" :size="17" /></span><div>{{ __('Create and activate an academic session before collecting fees.') }}</div></div>
    @endif

    <div class="stat-grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
        <x-stat-card :label="__('Page outstanding')" :value="money($dues->sum())" icon="wallet" tone="pending" :meta="$enrollments->total().' '.__('active students')" />
        <x-stat-card :label="__('Recent receipts')" :value="$recent->count()" icon="receipt" tone="collection" :meta="__('Last 8 completed payments')" />
        <x-stat-card :label="__('Allocation rule')" value="{{ __('Oldest first') }}" icon="clock" tone="student" :meta="__('Automatic against unpaid dues')" />
    </div>

    <div class="grid-main-side">
        <div class="card">
            <div class="card-header">
                <div><h3 class="card-title">{{ __('Select a student') }}</h3><p class="card-sub">{{ __('Search by name, Student ID or guardian phone') }}</p></div>
                <form method="GET" class="filter-bar" style="margin:0">
                    <div class="field grow" style="min-width:190px">
                        <input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search students') }}">
                    </div>
                    <x-button type="submit" variant="secondary" icon="search">{{ __('Search') }}</x-button>
                </form>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Class') }}</th><th>{{ __('Guardian') }}</th><th class="num">{{ __('Outstanding') }}</th><th class="actions">{{ __('Quick collect') }}</th></tr></thead>
                    <tbody>
                        @forelse ($enrollments as $enrollment)
                            @php($due = (float) ($dues[$enrollment->student_id] ?? 0))
                            <tr>
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
                                <td class="num {{ $due > 0 ? 'text-danger' : 'text-success' }}" style="font-weight:600">{{ money($due) }}</td>
                                <td class="actions">
                                    @if ($due > 0)
                                        <button type="button" class="btn btn-secondary btn-sm" @click="$dispatch('open-modal', 'quick-{{ $enrollment->student_id }}')">
                                            <x-icon name="zap" :size="13" /> {{ __('Collect') }}
                                        </button>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="table-empty">{{ __('No students match these filters.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <div class="card-header"><div><h3 class="card-title">{{ __('Recent quick receipts') }}</h3><p class="card-sub">{{ __('Last 8 completed payments') }}</p></div></div>
                <div class="card-body">
                    @forelse ($recent as $payment)
                        <div class="due-row">
                            <div class="due-info">
                                <span class="table-avatar">{{ Str::substr($payment->student?->full_name ?? '?', 0, 1) }}</span>
                                <div>
                                    <div class="cell-title">{{ $payment->student?->full_name }}</div>
                                    <div class="cell-sub">{{ $payment->receipt_no }} · {{ $payment->paid_at?->format('d M, h:i A') }}</div>
                                </div>
                            </div>
                            <div class="due-amount">{{ money($payment->amount) }}</div>
                        </div>
                    @empty
                        <p class="muted" style="font-size:12px;margin:0">{{ __('No quick collections recorded yet.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @foreach ($enrollments as $enrollment)
        @php($due = (float) ($dues[$enrollment->student_id] ?? 0))
        @if ($due > 0)
            @php($hasErrors = $errors->any() && (string) old('student_id') === (string) $enrollment->student_id)
            <x-modal :name="'quick-'.$enrollment->student_id" :title="__('Quick collect for').' '.$enrollment->student?->full_name" maxWidth="520px" :open="$hasErrors">
                <p class="modal-sub" style="margin:0 0 14px">{{ __('Outstanding') }}: {{ money($due) }} · {{ __('Oldest dues are cleared first') }}</p>

                @if ($hasErrors)
                    <div class="alert alert-warning" style="margin-bottom:12px">
                        <span class="alert-icon"><x-icon name="alert-triangle" :size="17" /></span>
                        <div>
                            <strong>{{ __('Please correct the highlighted fields') }}</strong>
                            <ul style="margin:6px 0 0;padding-left:18px">
                                @foreach ($errors->all() as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('fees.quick.store') }}"
                      x-data="quickAllocation(@js($allocationRows[$enrollment->student_id] ?? []), @js((float) old('amount', $due)))">
                    @csrf
                    <input type="hidden" name="student_id" value="{{ $enrollment->student_id }}">
                    <input type="hidden" name="paid_at" value="{{ now()->format('Y-m-d\TH:i') }}">

                    <div class="stack">
                        <x-input name="amount" :label="__('Amount to collect')" type="number" step="0.01" min="0.01" required x-model.number="amount" :help="__('Cannot exceed the outstanding balance for the selected months.')" />
                        <x-select name="payment_method" :label="__('Payment method')" :options="['cash' => __('Cash'), 'bank' => __('Bank'), 'mobile_banking' => __('Mobile banking'), 'cheque' => __('Cheque')]" :selected="old('payment_method', 'cash')" required />
                        <x-input name="reference_no" :label="__('Reference (optional)')" :placeholder="__('Receipt number, transaction ID...')" />
                        <label class="field">
                            <span class="field-label">{{ __('Note (optional)') }}</span>
                            <textarea class="textarea" name="note" rows="2" maxlength="500" placeholder="{{ __('Internal note') }}">{{ old('note') }}</textarea>
                        </label>
                        <label class="field" style="cursor:pointer;flex-direction:row;align-items:center;gap:8px">
                            <input type="checkbox" name="include_future" value="1" x-model="includeFuture">
                            <span>{{ __('Include future months (advance payment)') }}</span>
                        </label>
                        <p class="field-hint" style="margin:0">{{ __('Only past and current months are collected by default. Tick this to allocate the payment to later invoices too.') }}</p>
                    </div>

                    {{-- Live preview of how the amount will be applied --}}
                    <div class="card" style="margin-top:14px;background:var(--surface-2, rgba(124,92,255,.05));border:1px solid var(--line)">
                        <div class="card-header" style="padding:12px 14px">
                            <div>
                                <h4 class="card-title" style="font-size:13px">{{ __('Allocation preview') }}</h4>
                                <p class="card-sub">{{ __('Oldest dues are paid first, then the next oldest, until the amount runs out.') }}</p>
                            </div>
                            <span class="badge badge-warning" x-show="remaining > 0" x-cloak>
                                <span x-text="@js(__('Unallocated'))"></span>
                                <span x-text="fmt(remaining)"></span>
                            </span>
                            <span class="badge badge-success" x-show="remaining <= 0" x-cloak>{{ __('Fully allocated') }}</span>
                        </div>
                        <div class="table-wrap" style="max-height:230px;overflow:auto">
                            <table class="data-table" style="font-size:12px">
                                <thead><tr><th>{{ __('Fee') }}</th><th>{{ __('Due') }}</th><th class="num">{{ __('Balance') }}</th><th class="num">{{ __('Paying now') }}</th></tr></thead>
                                <tbody>
                                    <template x-for="row in allocation" :key="row.fee + (row.period || '') + row.balance">
                                        <tr>
                                            <td>
                                                <div class="cell-title" x-text="row.fee"></div>
                                                <div class="cell-sub" x-text="row.period || @js(__('One-time'))"></div>
                                            </td>
                                            <td class="muted" x-text="row.due || '—'"></td>
                                            <td class="num" x-text="fmt(row.balance)"></td>
                                            <td class="num" :style="row.pay > 0 ? 'color:var(--success);font-weight:700' : 'color:var(--muted)'" x-text="fmt(row.pay)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <p class="field-hint" style="margin:0;padding:10px 14px;border-top:1px solid var(--line)"
                           x-text="@js(__('Total applied')) + ': ' + fmt(totalApplied) + ' · ' + @js(__('Left after this payment')) + ': ' + fmt(leftAfter)"></p>
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px">
                        <button type="button" class="btn btn-ghost" @click="open = false">{{ __('Cancel') }}</button>
                        <x-button type="submit" variant="primary" icon="zap">{{ __('Collect and allocate') }}</x-button>
                    </div>
                </form>
            </x-modal>
        @endif
    @endforeach
@endsection

@push('scripts')
<script>
    function quickAllocation(rows, initial) {
        return {
            rows: Array.isArray(rows) ? rows : [],
            amount: Number(initial) || 0,
            includeFuture: false,

            fmt(value) {
                const number = Number(value) || 0;

                return '৳ ' + number.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },

            get visibleRows() {
                return this.rows.filter((row) => !row.future || this.includeFuture);
            },

            // Mirrors the server rule: oldest due first, then the next, until the
            // entered amount is used up.
            get allocation() {
                let remaining = Math.max(0, Number(this.amount) || 0);

                return this.visibleRows.map((row) => {
                    const balance = Math.max(0, Number(row.balance) || 0);
                    const pay = Math.min(balance, remaining);
                    remaining = Math.round((remaining - pay) * 100) / 100;

                    return { ...row, pay: Math.round(pay * 100) / 100 };
                });
            },

            get totalApplied() {
                return Math.round(this.allocation.reduce((sum, row) => sum + row.pay, 0) * 100) / 100;
            },

            get leftAfter() {
                const outstanding = this.visibleRows.reduce((sum, row) => sum + (Number(row.balance) || 0), 0);

                return Math.round(Math.max(0, outstanding - this.totalApplied) * 100) / 100;
            },

            get remaining() {
                return Math.round(Math.max(0, (Number(this.amount) || 0) - this.totalApplied) * 100) / 100;
            },
        };
    }
</script>
@endpush
