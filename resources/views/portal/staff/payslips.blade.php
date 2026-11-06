@extends('layouts.portal')

@section('title', 'Payslips')

@section('content')
    <section class="card">
        <div class="card-head"><div><h2>{{ __('My payslips') }}</h2><p>{{ __('Monthly salary statements') }}</p></div></div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>{{ __('Month') }}</th>
                        <th class="num">{{ __('Basic') }}</th>
                        <th class="num">{{ __('Allowances') }}</th>
                        <th class="num">{{ __('Deductions') }}</th>
                        <th class="num">{{ __('Net') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payrolls as $payroll)
                        <tr>
                            <td class="title-cell"><strong>{{ $payroll->month }}</strong><span>{{ $payroll->academicSession?->name }}</span></td>
                            <td class="num">{{ money($payroll->basic_salary) }}</td>
                            <td class="num">{{ money($payroll->total_allowances) }}</td>
                            <td class="num">{{ money($payroll->total_deductions) }}</td>
                            <td class="num"><strong>{{ money($payroll->net_salary) }}</strong></td>
                            <td><span class="badge {{ $payroll->status === 'paid' ? 'badge-success' : ($payroll->status === 'processed' ? 'badge-info' : 'badge-warning') }}">{{ ucfirst($payroll->status) }}</span></td>
                            <td><a class="btn btn-secondary btn-sm" href="{{ route('staff-portal.payslips.show', $payroll) }}">{{ __('View slip') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty">{{ __('No payslips have been generated yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="padding:12px 18px">{{ $payrolls->links() }}</div>
    </section>
@endsection
