@extends('layouts.app')

@section('title', 'Reports')

@section('content')
    <x-page-header eyebrow="Finance" title="Reports" :subtitle="$session ? 'Operational and financial reporting for the '.$session->name.' session.' : 'Choose a report. Configure an academic session for session-scoped figures.'">
        <x-slot:actions>
            @can('fees.collect')
                <x-button :href="route('fees.collection.index')" variant="secondary" icon="receipt">Fee collection</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid-3">
        @foreach ($reports as $key => $report)
            <a href="{{ route('reports.show', $key) }}" class="card" style="text-decoration:none;color:inherit;transition:border-color .15s ease, transform .15s ease">
                <div class="card-body">
                    <div style="display:flex;align-items:flex-start;gap:12px">
                        <span class="quick-icon violet" style="width:40px;height:40px;flex:0 0 40px"><x-icon :name="$report['icon']" :size="19" /></span>
                        <div style="min-width:0">
                            <div class="cell-title" style="font-size:13px">{{ $report['title'] }}</div>
                            <p class="muted" style="font-size:11.5px;line-height:1.55;margin:5px 0 10px">{{ $report['description'] }}</p>
                            <span class="badge badge-violet">Open report</span>
                        </div>
                    </div>
                </div>
            </a>
        @endforeach
    </div>

    <div class="card">
        <div class="card-body">
            <div class="info-box">
                Every report can be filtered and exported to CSV. Financial reports exclude reversed payments and archived expenses so totals reconcile with the fee ledger.
            </div>
        </div>
    </div>
@endsection
