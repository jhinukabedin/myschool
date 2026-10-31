@extends('layouts.app')

@section('title', 'Salary Report')

@section('content')
    <x-page-header eyebrow="Payroll" title="Salary Report" :subtitle="'Salary obligations for '.$year.($session ? ' · '.$session->name.' session' : ' · no academic session configured')">
        <x-slot:actions>
            <x-button :href="route('payroll.index')" variant="secondary" icon="arrow-left">Salary register</x-button>
            @can('payroll.view')
                <x-button :href="route('payroll.report.export', ['year' => $year])" variant="primary" icon="download">Export CSV</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="stat-grid" style="grid-template-columns:repeat(4,minmax(0,1fr))">
        <x-stat-card label="Net payroll" :value="money($totalNet)" icon="banknote" tone="student" :meta="$year" />
        <x-stat-card label="Paid" :value="money($totalPaid)" icon="check-circle" tone="collection" meta="Completed salary payments" />
        <x-stat-card label="Outstanding" :value="money($totalOutstanding)" icon="clock" tone="pending" meta="Pending or processed" />
        <x-stat-card label="Staff paid" :value="number_format($staffCount)" icon="users" tone="expense" meta="Distinct employees" />
    </div>

    <div class="chart-grid">
        <div class="card chart-card">
            <div class="card-header">
                <div><h3 class="card-title">Monthly Net Payroll</h3><p class="card-sub">Net salary and paid amount by month</p></div>
                <form method="GET" action="{{ route('payroll.report') }}" class="filter-bar" style="margin:0">
                    <div class="field">
                        <input class="input" type="number" name="year" value="{{ $year }}" min="2000" max="2100" aria-label="Report year" style="width:110px">
                    </div>
                    <x-button type="submit" variant="secondary" icon="filter">Apply</x-button>
                </form>
            </div>
            <div class="card-body">
                @if ($byMonth->isNotEmpty())
                    <div class="chart-wrap"><canvas id="payrollChart"></canvas></div>
                @else
                    <x-empty icon="banknote" title="No payroll for {{ $year }}" message="Generate payroll for a month to populate this report." />
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Monthly Breakdown</h3><p class="card-sub">Payroll rows and amounts</p></div></div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Month</th><th class="num">Staff</th><th class="num">Net payroll</th><th class="num">Paid</th><th class="num">Outstanding</th></tr></thead>
                    <tbody>
                        @forelse ($byMonth as $row)
                            <tr>
                                <td>{{ $row['month'] }}</td>
                                <td class="num">{{ $row['staff'] }}</td>
                                <td class="num">{{ money($row['net']) }}</td>
                                <td class="num text-success">{{ money($row['paid']) }}</td>
                                <td class="num {{ $row['outstanding'] > 0 ? 'text-danger' : '' }}">{{ money($row['outstanding']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="table-empty">No payroll rows for {{ $year }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div><h3 class="card-title">Salary Register</h3><p class="card-sub">{{ count($rows) }} payroll row(s) in {{ $year }}</p></div>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Staff</th><th>Employee no</th><th>Month</th><th class="num">Basic</th><th class="num">Allowances</th><th class="num">Deductions</th><th class="num">Net</th><th class="num">Paid</th><th class="num">Outstanding</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td><div class="cell-title">{{ $row['staff'] }}</div></td>
                            <td class="mono">{{ $row['employee_no'] }}</td>
                            <td>{{ $row['month'] }}</td>
                            <td class="num">{{ money($row['basic']) }}</td>
                            <td class="num text-success">{{ money($row['allowances']) }}</td>
                            <td class="num text-danger">{{ money($row['deductions']) }}</td>
                            <td class="num" style="font-weight:600">{{ money($row['net']) }}</td>
                            <td class="num text-success">{{ money($row['paid']) }}</td>
                            <td class="num {{ (float) $row['outstanding'] > 0 ? 'text-danger' : '' }}">{{ money($row['outstanding']) }}</td>
                            <td><x-badge :color="$row['status'] === 'Paid' ? 'success' : ($row['status'] === 'Partially paid' ? 'warning' : ($row['status'] === 'Processed' ? 'info' : 'neutral'))">{{ $row['status'] }}</x-badge></td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="table-empty">No salary records found for {{ $year }}.</td></tr>
                    @endforelse
                </tbody>
                @if (count($rows))
                    <tfoot>
                        <tr>
                            <th colspan="6" style="text-align:right">Total net payroll</th>
                            <th class="num">{{ money($totalNet) }}</th>
                            <th class="num">{{ money($totalPaid) }}</th>
                            <th class="num">{{ money($totalOutstanding) }}</th>
                            <th></th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const canvas = document.getElementById('payrollChart');
        if (!canvas) return;

        const months = @json($byMonth->pluck('month')->values());
        const net = @json($byMonth->pluck('net')->values());
        const paid = @json($byMonth->pluck('paid')->values());

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: months,
                datasets: [
                    {label: 'Net payroll', data: net, backgroundColor: 'rgba(139, 92, 246, 0.65)', borderColor: '#a78bfa', borderWidth: 1, borderRadius: 5},
                    {label: 'Paid', data: paid, backgroundColor: 'rgba(16, 185, 129, 0.6)', borderColor: '#34d399', borderWidth: 1, borderRadius: 5},
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {labels: {color: '#a79bc5', boxWidth: 9, boxHeight: 9, font: {size: 10}}},
                    tooltip: {callbacks: {label: (ctx) => ctx.dataset.label + ': ' + Number(ctx.raw).toLocaleString()}}
                },
                scales: {
                    x: {grid: {display: false}, ticks: {color: '#a79bc5', font: {size: 10}}},
                    y: {grid: {color: 'rgba(167, 139, 250, 0.10)'}, ticks: {color: '#a79bc5', font: {size: 10}, callback: (value) => Number(value).toLocaleString()}}
                }
            }
        });
    });
</script>
@endpush
