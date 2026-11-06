@extends('layouts.app')

@section('title', __('Other Income'))

@section('content')
    <x-page-header eyebrow="{{ __('Finance') }}" title="{{ __('Other Income') }}" :subtitle="$session ? __('Other income recorded for the :session session.', ['session' => $session->name]) : __('No academic session is configured.')">
        <x-slot:actions>
            @can('reports.view')
                <x-button :href="route('reports.show', 'income')" variant="secondary" icon="pie-chart">{{ __('Income report') }}</x-button>
            @endcan
            @can('finance.manage')
                <button type="button" class="btn btn-secondary" @click="$dispatch('open-modal', 'add-category')"><x-icon name="tag" :size="15" /> {{ __('Add category') }}</button>
                <x-button :href="route('other-incomes.create')" variant="primary" icon="plus">{{ __('Record income') }}</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="stat-grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
        <x-stat-card :label="__('Total income')" :value="money($summary['total'])" icon="circle-dollar" tone="collection" :meta="$session?->name ?? __('All sessions')" />
        <x-stat-card :label="__('This month')" :value="money($summary['month'])" icon="calendar" tone="student" :meta="now()->format('F Y')" />
        <x-stat-card :label="__('Income entries')" :value="number_format($summary['count'])" icon="list" tone="pending" :meta="__('Recorded line items')" />
    </div>

    <div class="grid-main-side">
        <div class="card">
            <div class="card-header">
                <div><h3 class="card-title">{{ __('Income Register') }}</h3><p class="card-sub">{{ $incomes->total() }} {{ __('record(s)') }}</p></div>
            </div>
            <div class="form-section" style="border-bottom:1px solid var(--line);border-top:0">
                <form method="GET" action="{{ route('other-incomes.index') }}" class="filter-bar" style="margin:0">
                    <div class="field grow"><input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search title...') }}" aria-label="{{ __('Search income') }}"></div>
                    <div class="field">
                        <select class="select" name="category_id" aria-label="{{ __('Filter category') }}">
                            <option value="">{{ __('All categories') }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field"><input class="input" type="date" name="from" value="{{ request('from') }}" aria-label="{{ __('From date') }}"></div>
                    <div class="field"><input class="input" type="date" name="to" value="{{ request('to') }}" aria-label="{{ __('To date') }}"></div>
                    <x-button type="submit" variant="secondary" icon="filter">{{ __('Filter') }}</x-button>
                    @if (request()->hasAny(['q', 'category_id', 'from', 'to']))
                        <x-button :href="route('other-incomes.index')" variant="ghost">{{ __('Reset') }}</x-button>
                    @endif
                </form>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>{{ __('Income') }}</th><th>{{ __('Category') }}</th><th>{{ __('Date') }}</th><th>{{ __('Method') }}</th><th class="num">{{ __('Items') }}</th><th class="num">{{ __('Amount') }}</th><th>{{ __('Recorded by') }}</th><th class="actions">{{ __('Actions') }}</th></tr></thead>
                    <tbody>
                        @forelse ($incomes as $income)
                            <tr>
                                <td>
                                    <div class="cell-title">{{ $income->title }}</div>
                                    <div class="cell-sub">
                                        {{ $income->reference ? __('Ref').' '.$income->reference : __('No reference') }}
                                    </div>
                                </td>
                                <td>{{ $income->category?->name ?? __('Uncategorised') }}</td>
                                <td>{{ $income->income_date?->format('d M Y') }}</td>
                                <td>{{ $income->reference ? __('Recorded') : '—' }}</td>
                                <td class="num">
                                    <span class="badge badge-muted" title="{{ $income->items->pluck('description')->filter()->join(', ') }}">{{ $income->items_count }}</span>
                                </td>
                                <td class="num" style="font-weight:600">{{ money($income->amount) }}</td>
                                <td>
                                    <div>{{ $income->recordedBy?->name ?? __('System') }}</div>
                                    <div class="cell-sub">{{ $income->created_at?->format('d M Y') }}</div>
                                </td>
                                <td class="actions">
                                    @can('finance.manage')
                                        <a href="{{ route('other-incomes.edit', $income) }}" class="btn-icon" title="{{ __('Edit income') }}"><x-icon name="pencil" :size="15" /></a>
                                        <form method="POST" action="{{ route('other-incomes.destroy', $income) }}" onsubmit="return confirm(@js(__('Archive this income? It will be removed from active reporting but retained for audit.')))" style="display:inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-icon" title="{{ __('Archive income') }}"><x-icon name="archive" :size="15" /></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="table-empty">{{ __('No income records match the current filters.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $incomes->links() }}
        </div>

        <div class="stack">
            <div class="card">
                <div class="card-header"><div><h3 class="card-title">{{ __('By Category') }}</h3><p class="card-sub">{{ __('Session totals') }}</p></div></div>
                <div class="card-body">
                    @forelse ($byCategory as $row)
                        <div class="due-row">
                            <div class="due-info">
                                <span class="quick-icon green" style="width:30px;height:30px"><x-icon name="tag" :size="14" /></span>
                                <div><div class="cell-title">{{ $row->category?->name ?? __('Uncategorised') }}</div></div>
                            </div>
                            <div class="due-amount">{{ money($row->total) }}</div>
                        </div>
                    @empty
                        <x-empty icon="circle-dollar" title="{{ __('No income') }}" message="{{ __('Record an income to see the category breakdown.') }}" />
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="info-box">
                        {{ __('Income records are soft-deleted. Archived records remain available for audit and reporting history, and financial data is never permanently removed from the ledger.') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    @can('finance.manage')
        <x-modal name="add-category" title="{{ __('Add income category') }}">
            <form method="POST" action="{{ route('other-incomes.categories.store') }}">
                @csrf
                <div class="stack">
                    <x-input name="name" :label="__('Category name')" required :placeholder="__('e.g. Donations')" />
                    <x-button type="submit" variant="primary" icon="plus" class="btn-block">{{ __('Add category') }}</x-button>
                </div>
            </form>
        </x-modal>
    @endcan
@endsection
