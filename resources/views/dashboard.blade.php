@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <x-page-header
        eyebrow="Overview"
        :title="'Welcome back, '.Str::before(auth()->user()->name, ' ')"
        :subtitle="$session ? 'Live figures for the '.$session->name.' academic session.' : 'Create an academic session to activate dashboard metrics.'"
    >
        <x-slot:actions>
            @can('fees.collect')
                <x-button :href="route('fees.quick.index')" variant="secondary" icon="zap">Quick collect</x-button>
            @endcan
            @can('students.create')
                <x-button :href="route('admissions.create')" variant="primary" icon="user-plus">New admission</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="stat-grid">
        <x-stat-card
            label="Active Students"
            :value="number_format($metrics['active_students'])"
            icon="users"
            tone="student"
            :meta="number_format($metrics['new_admissions']).' new this session'"
            trend="{{ $metrics['active_students'] > 0 ? 'Enrolled' : 'No enrolments' }}"
            direction="up"
        />
        <x-stat-card
            label="Fees Collected"
            :value="money($metrics['collected'])"
            icon="circle-dollar"
            tone="collection"
            :meta="money($metrics['collected_this_month']).' this month'"
            trend="Completed receipts"
            direction="up"
        />
        <x-stat-card
            label="Pending Fees"
            :value="money($metrics['pending'])"
            icon="wallet"
            tone="pending"
            :meta="$metrics['overdue_count'].' overdue fee rows'"
            trend="Outstanding"
            direction="down"
        />
        <x-stat-card
            label="Expenses"
            :value="money($metrics['expenses'])"
            icon="credit-card"
            tone="expense"
            :meta="money($metrics['expenses_this_month']).' this month'"
            trend="Operating"
            direction="flat"
        />
    </div>

    <div class="chart-grid">
        <div class="card chart-card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Fee Collection Trend</h3>
                    <p class="card-sub">Monthly completed receipts for the selected academic session</p>
                </div>
                <x-badge color="violet">{{ $session?->name ?? 'No session' }}</x-badge>
            </div>
            <div class="card-body">
                @if (array_sum($metrics['collection_chart']['values']) > 0)
                    <div class="chart-wrap"><canvas id="collectionChart"></canvas></div>
                @else
                    <div class="chart-empty">
                        <x-icon name="activity" :size="22" />
                        <div>No completed payments in this session yet.</div>
                    </div>
                @endif
            </div>
        </div>

        <div class="card chart-card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Students by Class</h3>
                    <p class="card-sub">Active enrolment distribution</p>
                </div>
            </div>
            <div class="card-body">
                @if (array_sum($metrics['students_by_class']['values']) > 0)
                    <div class="chart-wrap small"><canvas id="classChart"></canvas></div>
                    <div class="chart-legend" style="margin-top:14px">
                        <span class="legend-item"><span class="legend-dot" style="background:#8b5cf6"></span>Active students</span>
                    </div>
                @else
                    <div class="chart-empty">
                        <x-icon name="users" :size="22" />
                        <div>No active students enrolled.</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="grid-main-side">
        <div class="stack">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Recent Payments</h3>
                        <p class="card-sub">Latest completed fee receipts</p>
                    </div>
                    @can('fees.collect')
                        <a href="{{ route('fees.collection.index') }}" class="btn btn-ghost btn-sm">View ledger <x-icon name="arrow-right" :size="13" /></a>
                    @endcan
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr><th>Student</th><th>Receipt</th><th>Method</th><th class="num">Amount</th><th>Date</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse ($metrics['recent_payments'] as $payment)
                                <tr>
                                    <td>
                                        <div class="cell-main">
                                            <span class="table-avatar">{{ Str::of($payment->student?->full_name ?? '?')->substr(0, 1) }}</span>
                                            <div>
                                                <div class="cell-title">{{ $payment->student?->full_name }}</div>
                                                <div class="cell-sub">{{ $payment->student?->admission_no }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="mono" style="font-size:11px">{{ $payment->receipt_no }}</td>
                                    <td>{{ $payment->method_label }}</td>
                                    <td class="num text-success" style="font-weight:600">{{ money($payment->amount) }}</td>
                                    <td class="muted">{{ $payment->paid_at?->format('d M, h:i A') }}</td>
                                    <td class="actions">
                                        <a href="{{ route('payments.receipt', $payment) }}" class="btn-icon" title="Open receipt"><x-icon name="receipt" :size="15" /></a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="table-empty">No payments have been collected in this session.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Quick Actions</h3>
                        <p class="card-sub">Common daily operations</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="quick-grid">
                        @can('students.create')
                            <a href="{{ route('admissions.create') }}" class="quick-item">
                                <span class="quick-icon violet"><x-icon name="user-plus" :size="17" /></span>
                                <span class="quick-body"><span class="quick-title">New Admission</span><span class="quick-sub">Enrol a student</span></span>
                            </a>
                        @endcan
                        @can('fees.collect')
                            <a href="{{ route('fees.quick.index') }}" class="quick-item">
                                <span class="quick-icon green"><x-icon name="zap" :size="17" /></span>
                                <span class="quick-body"><span class="quick-title">Collect Fees</span><span class="quick-sub">Issue a receipt</span></span>
                            </a>
                        @endcan
                        @can('attendance.manage')
                            <a href="{{ route('attendance.index') }}" class="quick-item">
                                <span class="quick-icon blue"><x-icon name="calendar-check" :size="17" /></span>
                                <span class="quick-body"><span class="quick-title">Mark Attendance</span><span class="quick-sub">Today's register</span></span>
                            </a>
                        @endcan
                        @can('notices.manage')
                            <a href="{{ route('notices.create') }}" class="quick-item">
                                <span class="quick-icon amber"><x-icon name="bell" :size="17" /></span>
                                <span class="quick-body"><span class="quick-title">Publish Notice</span><span class="quick-sub">Notify the school</span></span>
                            </a>
                        @endcan
                        @can('marks.enter')
                            <a href="{{ route('exams.marks-entry') }}" class="quick-item">
                                <span class="quick-icon rose"><x-icon name="pencil" :size="17" /></span>
                                <span class="quick-body"><span class="quick-title">Enter Marks</span><span class="quick-sub">Exam results</span></span>
                            </a>
                        @endcan
                        @can('reports.view')
                            <a href="{{ route('reports.index') }}" class="quick-item">
                                <span class="quick-icon cyan"><x-icon name="pie-chart" :size="17" /></span>
                                <span class="quick-body"><span class="quick-title">View Reports</span><span class="quick-sub">Analytics hub</span></span>
                            </a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Top Defaulters</h3>
                        <p class="card-sub">Highest outstanding balances</p>
                    </div>
                    @can('fees.collect')
                        <a href="{{ route('fees.collection.index') }}" class="btn-icon"><x-icon name="arrow-right" :size="15" /></a>
                    @endcan
                </div>
                <div class="card-body">
                    @forelse ($metrics['defaulters'] as $row)
                        <div class="due-row">
                            <div class="due-info">
                                <span class="table-avatar">{{ Str::of($row['student']->full_name)->substr(0, 1) }}</span>
                                <div>
                                    <div class="cell-title">{{ $row['student']->full_name }}</div>
                                    <div class="cell-sub">
                                        {{ $row['student']->enrollments->first()?->class?->name ?? 'No class' }}
                                        · {{ $row['student']->admission_no }}
                                    </div>
                                </div>
                            </div>
                            <div class="due-amount">{{ money($row['due']) }}</div>
                        </div>
                    @empty
                        <x-empty icon="check-circle" title="No defaulters" message="Every enrolled student is up to date." />
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Attendance Today</h3>
                        <p class="card-sub">{{ now()->format('l, d M Y') }}</p>
                    </div>
                    <x-badge color="{{ $metrics['attendance_today']['percentage'] >= 80 ? 'success' : 'warning' }}">
                        {{ $metrics['attendance_today']['percentage'] }}%
                    </x-badge>
                </div>
                <div class="card-body">
                    @if ($metrics['attendance_today']['marked'] > 0)
                        <div class="kpi-row">
                            <div class="kpi"><div class="kpi-label">Present</div><div class="kpi-value text-success">{{ $metrics['attendance_today']['present'] }}</div></div>
                            <div class="kpi"><div class="kpi-label">Absent</div><div class="kpi-value text-danger">{{ $metrics['attendance_today']['absent'] }}</div></div>
                            <div class="kpi"><div class="kpi-label">Marked</div><div class="kpi-value">{{ $metrics['attendance_today']['marked'] }}</div></div>
                        </div>
                    @else
                        <x-empty icon="calendar-check" title="Not marked yet" message="Attendance for today has not been recorded." />
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Latest Notices</h3>
                        <p class="card-sub">Published communications</p>
                    </div>
                    @can('notices.view')
                        <a href="{{ route('notices.index') }}" class="btn-icon"><x-icon name="arrow-right" :size="15" /></a>
                    @endcan
                </div>
                <div class="card-body">
                    @forelse ($metrics['notices'] as $notice)
                        <div class="dropdown-notice" style="padding-left:0;padding-right:0">
                            <div class="t">{{ $notice->title }}</div>
                            <div class="d">{{ $notice->publish_at?->diffForHumans() }} · {{ $notice->audience_label }}</div>
                        </div>
                    @empty
                        <p class="muted" style="font-size:12px;margin:0">No notices published yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const collection = @json($metrics['collection_chart']);
        const classes = @json($metrics['students_by_class']);
        const grid = 'rgba(167, 139, 250, 0.10)';
        const tick = '#a79bc5';

        if (document.getElementById('collectionChart')) {
            new Chart(document.getElementById('collectionChart'), {
                type: 'line',
                data: {
                    labels: collection.labels,
                    datasets: [{
                        label: 'Collected',
                        data: collection.values,
                        borderColor: '#a78bfa',
                        backgroundColor: (context) => {
                            const chart = context.chart;
                            const {ctx, chartArea} = chart;
                            if (!chartArea) return 'rgba(139, 92, 246, 0.15)';
                            const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                            gradient.addColorStop(0, 'rgba(139, 92, 246, 0.45)');
                            gradient.addColorStop(1, 'rgba(139, 92, 246, 0.02)');
                            return gradient;
                        },
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#c4b5fd',
                        pointBorderColor: '#1b1134',
                        pointRadius: 3,
                        borderWidth: 2,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {legend: {display: false}, tooltip: {callbacks: {label: (ctx) => '৳ ' + Number(ctx.raw).toLocaleString()}}},
                    scales: {
                        x: {grid: {display: false}, ticks: {color: tick, font: {size: 10}}},
                        y: {grid: {color: grid}, ticks: {color: tick, font: {size: 10}, callback: (value) => '৳' + Number(value).toLocaleString()}}
                    }
                }
            });
        }

        if (document.getElementById('classChart')) {
            new Chart(document.getElementById('classChart'), {
                type: 'doughnut',
                data: {
                    labels: classes.labels,
                    datasets: [{
                        data: classes.values,
                        backgroundColor: ['#8b5cf6','#a855f7','#c084fc','#7c3aed','#6d28d9','#9333ea','#d8b4fe','#4c1d95','#a78bfa','#e9d5ff','#bfa4ff','#8b5cf6','#a855f7'],
                        borderColor: '#1b1134',
                        borderWidth: 3,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: {position: 'bottom', labels: {color: tick, boxWidth: 8, boxHeight: 8, padding: 10, font: {size: 9}}}
                    }
                }
            });
        }
    });
</script>
@endpush
