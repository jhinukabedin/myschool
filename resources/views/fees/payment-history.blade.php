@extends('layouts.app')

@section('title', __('Payment History'))

@section('content')
    <x-page-header eyebrow="{{ __('Fees') }}" title="{{ __('Payment History') }}" :subtitle="__('Every receipt issued by the school. Search by RCP number, student, Student ID or reference, then open or print the receipt.')">
        <x-slot:actions>
            <x-button :href="route('fees.collection.index')" variant="secondary" icon="receipt">{{ __('Fee Collection') }}</x-button>
            <x-button :href="route('fees.deletion-log')" variant="ghost" icon="trash">{{ __('Reversals') }}</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="stat-grid" style="grid-template-columns:repeat(4,minmax(0,1fr))">
        <x-stat-card :label="__('Receipts')" :value="number_format($totals['receipts'])" icon="receipt" tone="student" :meta="__('Matching the filters')" />
        <x-stat-card :label="__('Collected')" :value="money($totals['collected'])" icon="circle-dollar" tone="collection" :meta="__('Completed receipts')" />
        <x-stat-card :label="__('Scholarship conceded')" :value="money($totals['scholarship'])" icon="percent" tone="pending" :meta="__('Applied on these receipts')" />
        <x-stat-card :label="__('Reversed')" :value="money($totals['reversed'])" icon="undo" tone="expense" :meta="__('Cancelled / refunded')" />
    </div>

    <div class="card">
        <div class="card-header">
            <div><h3 class="card-title">{{ __('Receipts') }}</h3><p class="card-sub">{{ $payments->total() }} {{ __('receipt(s) found') }}</p></div>
            <form method="GET" class="filter-bar" style="margin:0;flex-wrap:wrap">
                <div class="field grow" style="min-width:200px">
                    <input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('RCP number, student, Student ID or reference') }}">
                </div>
                <div class="field">
                    <select class="select" name="session_id">
                        <option value="">{{ __('All sessions') }}</option>
                        @foreach ($sessions as $sessionOption)
                            <option value="{{ $sessionOption->id }}" @selected(request('session_id') == $sessionOption->id)>{{ $sessionOption->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <select class="select" name="method">
                        <option value="">{{ __('All methods') }}</option>
                        @foreach ($methods as $value => $label)
                            <option value="{{ $value }}" @selected(request('method') === $value)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <select class="select" name="status">
                        <option value="">{{ __('Any status') }}</option>
                        <option value="completed" @selected(request('status') === 'completed')>{{ __('Completed') }}</option>
                        <option value="reversed" @selected(request('status') === 'reversed')>{{ __('Reversed') }}</option>
                    </select>
                </div>
                <div class="field">
                    <input class="input" type="date" name="from" value="{{ request('from') }}" aria-label="{{ __('From date') }}">
                </div>
                <div class="field">
                    <input class="input" type="date" name="to" value="{{ request('to') }}" aria-label="{{ __('To date') }}">
                </div>
                <x-button type="submit" variant="secondary" icon="filter">{{ __('Filter') }}</x-button>
                @if (request()->hasAny(['q', 'session_id', 'method', 'status', 'from', 'to']))
                    <x-button :href="route('fees.payment-history')" variant="ghost" icon="x">{{ __('Reset') }}</x-button>
                @endif
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Receipt no') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Student') }}</th>
                        <th>{{ __('Session') }}</th>
                        <th>{{ __('Method') }}</th>
                        <th>{{ __('Reference') }}</th>
                        <th class="num">{{ __('Fees') }}</th>
                        <th class="num">{{ __('Amount') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="actions">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr>
                            <td class="mono" style="font-size:11px;white-space:nowrap">{{ $payment->receipt_no }}</td>
                            <td class="muted" style="white-space:nowrap">{{ $payment->paid_at?->format('d M Y, h:i A') }}</td>
                            <td>
                                <div class="cell-title">{{ $payment->student?->full_name ?? __('Unknown student') }}</div>
                                <div class="cell-sub">Student ID: {{ $payment->student?->student_code ?? 'â€”' }}</div>
                            </td>
                            <td>{{ $payment->academicSession?->name ?? 'â€”' }}</td>
                            <td>{{ $payment->method_label }}</td>
                            <td class="muted" style="font-size:12px">{{ $payment->reference_no ?: 'â€”' }}</td>
                            <td class="num">{{ $payment->allocations_count }}</td>
                            <td class="num" style="font-weight:600">{{ money($payment->amount) }}</td>
                            <td>
                                <x-badge :color="$payment->status === 'completed' ? 'success' : 'danger'">{{ ucfirst($payment->status) }}</x-badge>
                            </td>
                            <td class="actions" style="white-space:nowrap">
                                <button type="button" class="btn btn-secondary btn-sm"
                                        @click="$store.paymentDetails.load({{ $payment->id }})">
                                    <x-icon name="eye" :size="13" /> {{ __('Details') }}
                                </button>
                                <a href="{{ route('payments.receipt', ['payment' => $payment, 'print' => 1]) }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm" title="{{ __('Print receipt') }}">
                                    <x-icon name="printer" :size="13" /> {{ __('Print') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="table-empty">{{ __('No receipts match these filters.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $payments->links() }}
    </div>

    {{-- Receipt details modal. State lives in an Alpine store so it survives
         x-teleport (a nested x-data wrapper would lose scope inside the modal). --}}
    <x-modal name="payment-details" :title="__('Receipt details')" maxWidth="760px">
        <template x-if="$store.paymentDetails.loading">
            <p class="muted" style="margin:0">{{ __('Loading…') }}</p>
        </template>

        <template x-if="!$store.paymentDetails.loading && $store.paymentDetails.error">
            <div class="alert alert-warning">
                <span class="alert-icon"><x-icon name="alert-triangle" :size="17" /></span>
                <div x-text="$store.paymentDetails.error"></div>
            </div>
        </template>

        <template x-if="!$store.paymentDetails.loading && !$store.paymentDetails.error && $store.paymentDetails.data">
            <div>
                {{-- Compact header: receipt + date + status --}}
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px;font-size:12px">
                    <span class="mono" style="font-weight:700" x-text="$store.paymentDetails.data.receipt_no"></span>
                    <span class="muted">·</span>
                    <span class="muted" x-text="$store.paymentDetails.data.paid_at || $store.paymentDetails.dash"></span>
                    <span class="badge" :class="$store.paymentDetails.data.status === 'completed' ? 'badge-success' : 'badge-danger'"
                          style="text-transform:capitalize"
                          x-text="$store.paymentDetails.data.status"></span>
                </div>

                {{-- Two-column summary like a receipt slip --}}
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:6px 26px;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid var(--line)">
                    <div class="detail-row"><span class="detail-label">{{ __('Student Name') }}</span><span class="detail-value" x-text="$store.paymentDetails.data.student?.name || $store.paymentDetails.dash"></span></div>
                    <div class="detail-row"><span class="detail-label">{{ __('Student ID') }}</span><span class="detail-value mono" x-text="$store.paymentDetails.data.student?.student_code || $store.paymentDetails.dash"></span></div>
                    <div class="detail-row"><span class="detail-label">{{ __('Class & Section') }}</span><span class="detail-value" x-text="$store.paymentDetails.classSection"></span></div>
                    <div class="detail-row"><span class="detail-label">{{ __('Academic Session') }}</span><span class="detail-value" x-text="$store.paymentDetails.data.session || $store.paymentDetails.dash"></span></div>
                    <div class="detail-row"><span class="detail-label">{{ __('Guardian Name') }}</span><span class="detail-value" x-text="$store.paymentDetails.data.student?.guardian || $store.paymentDetails.dash"></span></div>
                    <div class="detail-row"><span class="detail-label">{{ __('Payment Method') }}</span><span class="detail-value" x-text="$store.paymentDetails.data.method || $store.paymentDetails.dash"></span></div>
                </div>

                <div class="muted" style="font-size:11.5px;display:flex;gap:16px;flex-wrap:wrap;margin-bottom:12px">
                    <span>{{ __('Reference') }}: <strong x-text="$store.paymentDetails.data.reference_no || $store.paymentDetails.dash"></strong></span>
                    <span>{{ __('Received by') }}: <strong x-text="$store.paymentDetails.data.received_by || $store.paymentDetails.dash"></strong></span>
                    <span x-show="$store.paymentDetails.data.student?.roll !== null && $store.paymentDetails.data.student?.roll !== undefined">{{ __('Roll') }}: <strong x-text="$store.paymentDetails.data.student?.roll"></strong></span>
                </div>

                <div class="table-wrap" style="max-height:260px;overflow:auto">
                    <table class="data-table" style="font-size:12px">
                        <thead><tr><th>{{ __('Fee') }}</th><th>{{ __('Period') }}</th><th class="num">{{ __('Scholarship') }}</th><th class="num">{{ __('Amount') }}</th></tr></thead>
                        <tbody>
                            <template x-for="(line, index) in $store.paymentDetails.data.lines" :key="index">
                                <tr>
                                    <td><div class="cell-title" x-text="line.fee"></div></td>
                                    <td class="muted" x-text="line.period || $store.paymentDetails.labels.oneTime"></td>
                                    <td class="num" x-text="line.scholarship > 0 ? $store.paymentDetails.fmt(line.scholarship) : $store.paymentDetails.dash"></td>
                                    <td class="num" x-text="$store.paymentDetails.fmt(line.amount)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-top:14px;flex-wrap:wrap">
                    <div>
                        <div class="muted" style="font-size:12px" x-show="$store.paymentDetails.data.discount_amount > 0">
                            {{ __('Scholarship conceded') }}: <strong x-text="$store.paymentDetails.fmt($store.paymentDetails.data.discount_amount)"></strong>
                        </div>
                        <div style="font-weight:700">{{ __('Total paid') }}: <span x-text="$store.paymentDetails.fmt($store.paymentDetails.data.amount)"></span></div>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <a :href="$store.paymentDetails.data.receipt_url" class="btn btn-ghost btn-sm">{{ __('Open full receipt') }}</a>
                        <a :href="$store.paymentDetails.data.print_url" target="_blank" rel="noopener" class="btn btn-primary btn-sm">
                            <x-icon name="printer" :size="13" /> {{ __('Print') }}
                        </a>
                    </div>
                </div>

                <template x-if="$store.paymentDetails.data.reversals && $store.paymentDetails.data.reversals.length">
                    <div class="alert alert-warning" style="margin-top:14px">
                        <span class="alert-icon"><x-icon name="alert-triangle" :size="17" /></span>
                        <div>
                            <strong>{{ __('Reversed payment') }}</strong>
                            <template x-for="(rev, i) in $store.paymentDetails.data.reversals" :key="i">
                                <div style="font-size:12px" x-text="rev.type + ' · ' + rev.at + ' · ' + rev.by + ' — ' + rev.reason"></div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </x-modal>
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        const labels = {
            oneTime: @js(__('One-time')),
            roll: @js(__('Roll')),
            failed: @js(__('Could not load this receipt. Please try again.')),
        };

        Alpine.store('paymentDetails', {
            loading: false,
            error: null,
            data: null,
            dash: '—',
            labels,

            fmt(value) {
                return '৳ ' + (Number(value) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },

            get classSection() {
                if (!this.data || !this.data.student) {
                    return this.dash;
                }

                const parts = [this.data.student.class, this.data.student.section].filter(Boolean);

                return parts.length ? parts.join(' - ') : this.dash;
            },

            async load(paymentId) {
                this.data = null;
                this.error = null;
                this.loading = true;

                window.dispatchEvent(new CustomEvent('open-modal', { detail: 'payment-details' }));

                try {
                    const response = await fetch('/payments/' + paymentId + '/details', {
                        headers: { 'Accept': 'application/json' },
                    });

                    if (!response.ok) {
                        throw new Error(labels.failed);
                    }

                    this.data = await response.json();
                } catch (exception) {
                    this.error = exception.message || labels.failed;
                } finally {
                    this.loading = false;
                }
            },
        });
    });
</script>
@endpush
