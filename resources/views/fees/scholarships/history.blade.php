@extends('layouts.app')

@section('title', __('Discount History'))

@section('content')
    <x-page-header eyebrow="{{ __('Fees') }}" title="{{ __('Discount History') }}" :subtitle="$scholarship->scholarship_name.' · '.$scholarship->student?->full_name">
        <x-slot:actions>
            <x-button :href="route('fees.scholarships.show', $scholarship)" variant="secondary" icon="eye">{{ __('View') }}</x-button>
            <x-button :href="route('fees.scholarships.index')" variant="ghost" icon="arrow-left">{{ __('Back') }}</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <div class="card-header"><div><h3 class="card-title">{{ __('Invoice Adjustments') }}</h3></div></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Invoice') }}</th><th class="num">{{ __('Original') }}</th><th class="num">{{ __('Discount') }}</th><th class="num">{{ __('Payable') }}</th><th>{{ __('Reason') }}</th><th>{{ __('By') }}</th></tr></thead>
                <tbody>
                    @forelse ($scholarship->adjustments as $adjustment)
                        <tr>
                            <td>{{ $adjustment->created_at->format('d M Y, h:i A') }}</td>
                            <td>#{{ $adjustment->student_fee_assignment_id }}</td>
                            <td class="num">{{ money($adjustment->original_amount) }}</td>
                            <td class="num text-success">{{ money($adjustment->discount_amount) }}</td>
                            <td class="num">{{ money($adjustment->payable_amount) }}</td>
                            <td class="muted" style="font-size:12px">{{ $adjustment->adjustment_reason }}</td>
                            <td>{{ $adjustment->creator?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="table-empty">{{ __('No adjustments recorded yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><div><h3 class="card-title">{{ __('Modification History') }}</h3></div></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Action') }}</th><th>{{ __('Description') }}</th><th>{{ __('By') }}</th></tr></thead>
                <tbody>
                    @forelse ($audit as $log)
                        <tr>
                            <td>{{ $log->created_at->format('d M Y, h:i A') }}</td>
                            <td><span class="mono" style="font-size:11px">{{ $log->action ?? $log->event ?? '' }}</span></td>
                            <td>{{ $log->description }}</td>
                            <td>{{ $log->user?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="table-empty">{{ __('No history yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
