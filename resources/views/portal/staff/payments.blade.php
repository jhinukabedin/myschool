@extends('layouts.portal')

@section('title', 'Payment History')

@section('content')
    <section class="card">
        <div class="card-head"><div><h2>{{ __('Salary payment history') }}</h2><p>{{ __('Every installment recorded by the school') }}</p></div></div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Month') }}</th>
                        <th>{{ __('Method') }}</th>
                        <th>{{ __('Reference') }}</th>
                        <th class="num">{{ __('Amount') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr>
                            <td>{{ $payment->paid_at?->format('d M Y, h:i A') }}</td>
                            <td>{{ $payment->payroll?->month }}</td>
                            <td>{{ ucwords(str_replace('_', ' ', (string) $payment->payment_method)) }}</td>
                            <td class="mono" style="font-size:11px">{{ $payment->reference_no ?: '—' }}</td>
                            <td class="num"><strong>{{ money($payment->amount) }}</strong></td>
                            <td>
                                <span class="badge {{ $payment->status === 'completed' ? 'badge-success' : 'badge-danger' }}">{{ ucfirst($payment->status) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">{{ __('No salary payments recorded yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="padding:12px 18px">{{ $payments->links() }}</div>
    </section>
@endsection
