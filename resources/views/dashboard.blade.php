@extends('layouts.app')

@section('title', __('Dashboard'))

@section('content')
    <x-page-header
        eyebrow="{{ __('Overview') }}"
        :title="__('Welcome back')"
        :subtitle="$session ? __('Live figures for the :session academic session.', ['session' => $session->name]) : __('Create an academic session to activate dashboard metrics.')"
    >
        <x-slot:actions>
            @if (feature_enabled('fee-collection'))
                @can('fees.collect')
                    <x-button :href="route('fees.quick.index')" variant="secondary" icon="zap">{{ __('Quick collect') }}</x-button>
                @endcan
            @endif
            @if (feature_enabled('student-records'))
                @can('students.create')
                    <x-button :href="route('admissions.create')" variant="primary" icon="user-plus">{{ __('New admission') }}</x-button>
                @endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    @php
        $moduleAccess = app(\App\Services\ModuleAccess::class);
        $lockedModules = auth()->user()?->isSuperAdmin() ? collect() : $moduleAccess->lockedModules();
        $showStudentStats = module_enabled('students');
        $showFeeStats = module_enabled('fees');
        $showFinanceStats = module_enabled('finance');
    @endphp

    @if ($lockedModules->isNotEmpty())
        <div class="info-box" style="margin-bottom:16px;border-left:4px solid #d9ad55">
            <strong>{{ $lockedModules->count() }} {{ Str::plural('module', $lockedModules->count()) }} locked.</strong>
            Website content management remains available.
            @can('subscription.view')
                <a href="{{ route('subscription.index') }}">View subscription &amp; modules</a>
            @endcan
        </div>
    @endif

    @if ($showStudentStats || $showFeeStats || $showFinanceStats)
        <div class="stat-grid">
            @if ($showStudentStats)
                <x-stat-card
                    :label="__('Active Students')"
                    :value="number_format($metrics['active_students'])"
                    icon="users"
                    tone="student"
                    :meta="__(':count new this session', ['count' => number_format($metrics['new_admissions'])])"
                    :trend="$metrics['active_students'] > 0 ? __('Enrolled') : __('No enrolments')"
                    direction="up"
                />
            @endif
            @if ($showFeeStats)
                <x-stat-card
                    :label="__('Fees Collected')"
                    :value="money($metrics['collected'])"
                    icon="circle-dollar"
                    tone="collection"
                    :meta="__(':amount this month', ['amount' => money($metrics['collected_this_month'])])"
                    :trend="__('Completed receipts')"
                    direction="up"
                />
                <x-stat-card
                    :label="__('Pending Fees')"
                    :value="money($metrics['pending'])"
                    icon="wallet"
                    tone="pending"
                    :meta="__(':count overdue fee rows', ['count' => $metrics['overdue_count']])"
                    :trend="__('Outstanding')"
                    direction="down"
                />
            @endif
            @if ($showFinanceStats)
                <x-stat-card
                    :label="__('Expenses')"
                    :value="money($metrics['expenses'])"
                    icon="credit-card"
                    tone="expense"
                    :meta="__(':amount this month', ['amount' => money($metrics['expenses_this_month'])])"
                    :trend="__('Operating')"
                    direction="flat"
                />
            @endif
        </div>
    @endif

    @if ($showFeeStats || $showStudentStats)
        <div class="chart-grid">
            @if ($showFeeStats)
                <div class="card chart-card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">{{ __('Fee Collection Trend') }}</h3>
                            <p class="card-sub">{{ __('Monthly completed receipts for the selected academic session') }}</p>
                        </div>
                        <x-badge color="violet">{{ $session?->name ?? __('No session') }}</x-badge>
                    </div>
                    <div class="card-body">
                        @if (array_sum($metrics['collection_chart']['values']) > 0)
                            <div class="chart-wrap"><canvas id="collectionChart"></canvas></div>
                        @else
                            <div class="chart-empty">
                                <x-icon name="activity" :size="22" />
                                <div>{{ __('No completed payments in this session yet.') }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            @if ($showStudentStats)
                <div class="card chart-card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">{{ __('Students by Class') }}</h3>
                            <p class="card-sub">{{ __('Active enrolment distribution') }}</p>
                        </div>
                    </div>
                    <div class="card-body">
                        @if (array_sum($metrics['students_by_class']['values']) > 0)
                            <div class="chart-wrap small"><canvas id="classChart"></canvas></div>
                            <div class="chart-legend" style="margin-top:14px">
                                <span class="legend-item"><span class="legend-dot" style="background:#8b5cf6"></span>{{ __('Active students') }}</span>
                            </div>
                        @else
                            <div class="chart-empty">
                                <x-icon name="users" :size="22" />
                                <div>{{ __('No active students enrolled.') }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="grid-main-side">
        <div class="stack">
            @if (module_enabled('fees'))
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('Recent Payments') }}</h3>
                        <p class="card-sub">{{ __('Latest completed fee receipts') }}</p>
                    </div>
                    @can('fees.collect')
                        <a href="{{ route('fees.collection.index') }}" class="btn btn-ghost btn-sm">{{ __('View ledger') }} <x-icon name="arrow-right" :size="13" /></a>
                    @endcan
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr><th>{{ __('Student') }}</th><th>{{ __('Receipt') }}</th><th>{{ __('Method') }}</th><th class="num">{{ __('Amount') }}</th><th>{{ __('Date') }}</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse ($metrics['recent_payments'] as $payment)
                                <tr>
                                    <td>
                                        <div class="cell-main">
                                            <span class="table-avatar">{{ Str::of($payment->student?->full_name ?? '?')->substr(0, 1) }}</span>
                                            <div>
                                                <div class="cell-title">{{ $payment->student?->full_name }}</div>
                                                <div class="cell-sub">Student ID: {{ $payment->student?->student_code }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="mono" style="font-size:11px">{{ $payment->receipt_no }}</td>
                                    <td>{{ $payment->method_label }}</td>
                                    <td class="num text-success" style="font-weight:600">{{ money($payment->amount) }}</td>
                                    <td class="muted">{{ $payment->paid_at?->format('d M, h:i A') }}</td>
                                    <td class="actions">
                                        <a href="{{ route('payments.receipt', $payment) }}" class="btn-icon" title="{{ __('Open receipt') }}"><x-icon name="receipt" :size="15" /></a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="table-empty">{{ __('No payments have been collected in this session.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('Quick Actions') }}</h3>
                        <p class="card-sub">{{ __('Common daily operations') }}</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="quick-grid">
                        @if (feature_enabled('student-records'))
                            @can('students.create')
                                <a href="{{ route('admissions.create') }}" class="quick-item">
                                    <span class="quick-icon violet"><x-icon name="user-plus" :size="17" /></span>
                                    <span class="quick-body"><span class="quick-title">{{ __('New Admission') }}</span><span class="quick-sub">{{ __('Enrol a student') }}</span></span>
                                </a>
                            @endcan
                        @endif
                        @if (feature_enabled('fee-collection'))
                            @can('fees.collect')
                                <a href="{{ route('fees.quick.index') }}" class="quick-item">
                                    <span class="quick-icon green"><x-icon name="zap" :size="17" /></span>
                                    <span class="quick-body"><span class="quick-title">{{ __('Collect Fees') }}</span><span class="quick-sub">{{ __('Issue a receipt') }}</span></span>
                                </a>
                            @endcan
                        @endif
                        @if (feature_enabled('attendance'))
                            @can('attendance.manage')
                                <a href="{{ route('attendance.index') }}" class="quick-item">
                                    <span class="quick-icon blue"><x-icon name="calendar-check" :size="17" /></span>
                                    <span class="quick-body"><span class="quick-title">{{ __('Mark Attendance') }}</span><span class="quick-sub">{{ __("Today's register") }}</span></span>
                                </a>
                            @endcan
                        @endif
                        @if (feature_enabled('notices'))
                            @can('notices.manage')
                                <a href="{{ route('notices.create') }}" class="quick-item">
                                    <span class="quick-icon amber"><x-icon name="bell" :size="17" /></span>
                                    <span class="quick-body"><span class="quick-title">{{ __('Publish Notice') }}</span><span class="quick-sub">{{ __('Notify the school') }}</span></span>
                                </a>
                            @endcan
                        @endif
                        @if (feature_enabled('exams'))
                            @can('marks.enter')
                                <a href="{{ route('exams.marks-entry') }}" class="quick-item">
                                    <span class="quick-icon rose"><x-icon name="pencil" :size="17" /></span>
                                    <span class="quick-body"><span class="quick-title">{{ __('Enter Marks') }}</span><span class="quick-sub">{{ __('Exam results') }}</span></span>
                                </a>
                            @endcan
                        @endif
                        @if (feature_enabled('reports'))
                            @can('reports.view')
                                <a href="{{ route('reports.index') }}" class="quick-item">
                                    <span class="quick-icon cyan"><x-icon name="pie-chart" :size="17" /></span>
                                    <span class="quick-body"><span class="quick-title">{{ __('View Reports') }}</span><span class="quick-sub">{{ __('Analytics hub') }}</span></span>
                                </a>
                            @endcan
                        @endif
                        @if (feature_enabled('website'))
                            @can('website.view')
                                <a href="{{ route('website-admin.index') }}" class="quick-item">
                                    <span class="quick-icon violet"><x-icon name="layout" :size="17" /></span>
                                    <span class="quick-body"><span class="quick-title">{{ __('Website Content') }}</span><span class="quick-sub">{{ __('Pages, menus and media') }}</span></span>
                                </a>
                            @endcan
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="stack">
            @if (module_enabled('fees'))
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('Top Defaulters') }}</h3>
                        <p class="card-sub">{{ __('Highest outstanding balances') }}</p>
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
                                        {{ $row['student']->enrollments->first()?->class?->name ?? __('No class') }}
                                        Â· Student ID: {{ $row['student']->student_code }}
                                    </div>
                                </div>
                            </div>
                            <div class="due-amount">{{ money($row['due']) }}</div>
                        </div>
                    @empty
                        <x-empty icon="check-circle" :title="__('No defaulters')" :message="__('Every enrolled student is up to date.')" />
                    @endforelse
                </div>
            </div>
            @endif

            @if (module_enabled('attendance'))
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('Attendance Today') }}</h3>
                        <p class="card-sub">{{ now()->translatedFormat('l, d M Y') }}</p>
                    </div>
                    <x-badge color="{{ $metrics['attendance_today']['percentage'] >= 80 ? 'success' : 'warning' }}">
                        {{ $metrics['attendance_today']['percentage'] }}%
                    </x-badge>
                </div>
                <div class="card-body">
                    @if ($metrics['attendance_today']['marked'] > 0)
                        <div class="kpi-row">
                            <div class="kpi"><div class="kpi-label">{{ __('Present') }}</div><div class="kpi-value text-success">{{ $metrics['attendance_today']['present'] }}</div></div>
                            <div class="kpi"><div class="kpi-label">{{ __('Absent') }}</div><div class="kpi-value text-danger">{{ $metrics['attendance_today']['absent'] }}</div></div>
                            <div class="kpi"><div class="kpi-label">{{ __('Marked') }}</div><div class="kpi-value">{{ $metrics['attendance_today']['marked'] }}</div></div>
                        </div>
                    @else
                        <x-empty icon="calendar-check" :title="__('Not marked yet')" :message="__('Attendance for today has not been recorded.')" />
                    @endif
                </div>
            </div>
            @endif

            @if (module_enabled('communication'))
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('Latest Notices') }}</h3>
                        <p class="card-sub">{{ __('Published communications') }}</p>
                    </div>
                    @can('notices.view')
                        <a href="{{ route('notices.index') }}" class="btn-icon"><x-icon name="arrow-right" :size="15" /></a>
                    @endcan
                </div>
                <div class="card-body">
                    @forelse ($metrics['notices'] as $notice)
                        <div class="dropdown-notice" style="padding-left:0;padding-right:0">
                            <div class="t">{{ $notice->title }}</div>
                            <div class="d">{{ $notice->publish_at?->diffForHumans() }} Â· {{ $notice->audience_label }}</div>
                        </div>
                    @empty
                        <p class="muted" style="font-size:12px;margin:0">{{ __('No notices published yet.') }}</p>
                    @endforelse
                </div>
            </div>
            @endif
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
        const currency = @json(__('à§³'));

        if (document.getElementById('collectionChart')) {
            new Chart(document.getElementById('collectionChart'), {
                type: 'line',
                data: {
                    labels: collection.labels,
                    datasets: [{
                        label: @json(__('Collected')),
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
                    plugins: {legend: {display: false}, tooltip: {callbacks: {label: (ctx) => currency + ' ' + Number(ctx.raw).toLocaleString()}}},
                    scales: {
                        x: {grid: {display: false}, ticks: {color: tick, font: {size: 10}}},
                        y: {grid: {color: grid}, ticks: {color: tick, font: {size: 10}, callback: (value) => currency + Number(value).toLocaleString()}}
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
