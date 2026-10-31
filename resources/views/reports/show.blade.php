@extends('layouts.app')

@section('title', $title)

@php
    $hasChart = false;
    if ($chart) {
        $hasChart = isset($chart['values'])
            ? collect($chart['values'])->sum() > 0
            : collect($chart['datasets'] ?? [])->contains(fn ($dataset) => collect($dataset['values'] ?? [])->sum() > 0);
    }
    $numericHeader = '/amount|total|net|paid|outstanding|gross|deduction|income|expense|salary|balance|collected|reversed|fee rows|students|present|late|absent|excused|entries|pass|fail|gpa|rate|%|count/i';
    $dateReports = in_array($reportKey, ['collection', 'income-expense', 'expenses', 'attendance', 'payroll'], true);
@endphp

@section('content')
    <x-page-header eyebrow="Reports" :title="$title" :subtitle="$description.' · '.$period">
        <x-slot:actions>
            <x-button :href="route('reports.index')" variant="secondary" icon="arrow-left">All reports</x-button>
            <x-button :href="route('reports.export', array_merge(['report' => $reportKey], request()->query()))" variant="primary" icon="download">Export CSV</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="tabs" style="margin-bottom:16px;overflow-x:auto">
        @foreach ($definitions as $key => $definition)
            <a href="{{ route('reports.show', $key) }}" class="tab {{ $key === $reportKey ? 'active' : '' }}" style="white-space:nowrap">{{ $definition['title'] }}</a>
        @endforeach
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="form-section" style="border-bottom:0">
            <form method="GET" action="{{ route('reports.show', $reportKey) }}" class="filter-bar" style="margin:0">
                @if ($dateReports)
                    <div class="field"><label class="field-label" for="from">From</label><input id="from" class="input" type="date" name="from" value="{{ $from }}"></div>
                    <div class="field"><label class="field-label" for="to">To</label><input id="to" class="input" type="date" name="to" value="{{ $to }}"></div>
                @endif

                @if ($reportKey === 'results')
                    <div class="field">
                        <label class="field-label" for="exam_id">Examination</label>
                        <select id="exam_id" class="select" name="exam_id">
                            @foreach ($exams as $option)
                                <option value="{{ $option->id }}" @selected(($exam?->id ?? null) === $option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="class_id">Class</label>
                        <select id="class_id" class="select" name="class_id">
                            <option value="">All classes</option>
                            @foreach ($classes as $class)
                                <option value="{{ $class->id }}" @selected(request('class_id') == $class->id)>{{ $class->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <x-button type="submit" variant="secondary" icon="filter">Apply filters</x-button>
                <span class="muted-2" style="font-size:11px;align-self:center">{{ $session?->name ?? 'No session' }} · {{ $period }}</span>
            </form>
        </div>
    </div>

    @if (count($summary))
        <div class="stat-grid" style="grid-template-columns:repeat({{ min(max(count($summary), 1), 4) }},minmax(0,1fr))">
            @foreach ($summary as $index => $item)
                <x-stat-card
                    :label="$item['label']"
                    :value="is_numeric($item['value']) ? number_format((float) $item['value']) : $item['value']"
                    :icon="['activity', 'trending-up', 'wallet', 'pie-chart'][$index % 4]"
                    :tone="['student', 'collection', 'pending', 'expense'][$index % 4]"
                />
            @endforeach
        </div>
    @endif

    <div class="chart-grid">
        <div class="card chart-card">
            <div class="card-header">
                <div><h3 class="card-title">{{ $title }} Overview</h3><p class="card-sub">{{ $period }}</p></div>
                <x-badge color="violet">{{ $session?->name ?? 'No session' }}</x-badge>
            </div>
            <div class="card-body">
                @if ($hasChart)
                    <div class="chart-wrap"><canvas id="reportChart"></canvas></div>
                @else
                    <x-empty icon="pie-chart" title="No chart data" message="There is no data for the selected period. Adjust the filters or record transactions." />
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Report Summary</h3><p class="card-sub">Key figures for the selected period</p></div></div>
            <div class="card-body">
                @if (count($summary))
                    <div class="detail-list">
                        @foreach ($summary as $item)
                            <div class="detail-row">
                                <span class="detail-label">{{ $item['label'] }}</span>
                                <span class="detail-value">{{ is_numeric($item['value']) ? number_format((float) $item['value']) : $item['value'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-empty icon="info" title="No summary" message="This report does not expose summary figures." />
                @endif
                <div class="info-box" style="margin-top:14px">
                    Figures use completed payments only. Reversed payments and archived expenses are excluded from revenue and expense totals.
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div><h3 class="card-title">{{ $title }} Data</h3><p class="card-sub">{{ count($rows) }} row(s) · {{ $period }}</p></div>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        @foreach ($columns as $column)
                            <th class="{{ preg_match($numericHeader, $column) ? 'num' : '' }}">{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            @foreach (array_values($row) as $index => $cell)
                                <td class="{{ preg_match($numericHeader, $columns[$index] ?? '') ? 'num' : '' }}">
                                    @if ($index === 0)
                                        <span class="cell-title">{{ $cell }}</span>
                                    @else
                                        {{ $cell }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ max(count($columns), 1) }}" class="table-empty">No data is available for this report and period. Adjust the filters and try again.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const canvas = document.getElementById('reportChart');
        if (!canvas) return;

        const chart = @json($chart);
        if (!chart) return;

        const palette = ['#a78bfa', '#34d399', '#fb7185', '#fbbf24', '#60a5fa', '#22d3ee', '#f472b6', '#c084fc'];
        const area = 'rgba(167, 139, 250, 0.16)';
        let datasets = [];

        if (chart.datasets && chart.datasets.length) {
            datasets = chart.datasets.map((dataset, index) => ({
                label: dataset.label,
                data: dataset.values,
                borderColor: dataset.color || palette[index % palette.length],
                backgroundColor: chart.type === 'line' ? 'transparent' : (dataset.color || palette[index % palette.length]),
                borderWidth: 2,
                tension: 0.35,
                pointRadius: 3,
                borderRadius: chart.type === 'bar' ? 5 : 0,
                fill: false,
            }));
        } else {
            const isLine = chart.type === 'line';
            datasets = [{
                label: chart.label || 'Total',
                data: chart.values || [],
                borderColor: palette[0],
                backgroundColor: isLine ? area : palette[0],
                borderWidth: 2,
                tension: 0.35,
                pointRadius: 3,
                borderRadius: isLine ? 0 : 5,
                fill: isLine,
            }];
        }

        new Chart(canvas, {
            type: chart.type === 'line' ? 'line' : 'bar',
            data: {labels: chart.labels || [], datasets},
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {display: datasets.length > 1, labels: {color: '#a79bc5', boxWidth: 9, boxHeight: 9, font: {size: 10}}},
                    tooltip: {callbacks: {label: (ctx) => ctx.dataset.label + ': ' + Number(ctx.raw).toLocaleString()}}
                },
                scales: {
                    x: {grid: {display: false}, ticks: {color: '#a79bc5', font: {size: 10}}},
                    y: {beginAtZero: true, grid: {color: 'rgba(167, 139, 250, 0.10)'}, ticks: {color: '#a79bc5', font: {size: 10}, callback: (value) => Number(value).toLocaleString()}}
                }
            }
        });
    });
</script>
@endpush
