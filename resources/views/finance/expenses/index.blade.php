@extends('layouts.app')

@section('title', 'Expenses')

@section('content')
    <x-page-header eyebrow="Finance" title="Expenses" :subtitle="$session ? 'Operating expenses recorded for the '.$session->name.' session.' : 'No academic session is configured.'">
        <x-slot:actions>
            @can('reports.view')
                <x-button :href="route('reports.show', 'expenses')" variant="secondary" icon="pie-chart">Expense report</x-button>
            @endcan
            @can('finance.manage')
                <button type="button" class="btn btn-secondary" @click="$dispatch('open-modal', 'add-category')"><x-icon name="tag" :size="15" /> Add category</button>
                <x-button :href="route('expenses.create')" variant="primary" icon="plus">Record expense</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="stat-grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
        <x-stat-card label="Total expenses" :value="money($summary['total'])" icon="credit-card" tone="expense" :meta="$session?->name ?? 'All sessions'" />
        <x-stat-card label="This month" :value="money($summary['month'])" icon="calendar" tone="student" :meta="now()->format('F Y')" />
        <x-stat-card label="Expense entries" :value="number_format($summary['count'])" icon="list" tone="pending" meta="Recorded line items" />
    </div>

    <div class="grid-main-side">
        <div class="card">
            <div class="card-header">
                <div><h3 class="card-title">Expense Register</h3><p class="card-sub">{{ $expenses->total() }} record(s) · archived entries are excluded</p></div>
            </div>
            <div class="form-section" style="border-bottom:1px solid var(--border);border-top:0">
                <form method="GET" action="{{ route('expenses.index') }}" class="filter-bar" style="margin:0">
                    <div class="field grow"><input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="Search title..." aria-label="Search expenses"></div>
                    <div class="field">
                        <select class="select" name="category_id" aria-label="Filter category">
                            <option value="">All categories</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field"><input class="input" type="date" name="from" value="{{ request('from') }}" aria-label="From date"></div>
                    <div class="field"><input class="input" type="date" name="to" value="{{ request('to') }}" aria-label="To date"></div>
                    <x-button type="submit" variant="secondary" icon="filter">Filter</x-button>
                    @if (request()->hasAny(['q', 'category_id', 'from', 'to']))
                        <x-button :href="route('expenses.index')" variant="ghost">Reset</x-button>
                    @endif
                </form>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Expense</th><th>Category</th><th>Date</th><th>Method</th><th class="num">Amount</th><th>Recorded by</th><th class="actions">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($expenses as $expense)
                            <tr>
                                <td>
                                    <div class="cell-title">{{ $expense->title }}</div>
                                    <div class="cell-sub">
                                        {{ $expense->reference_no ? 'Ref '.$expense->reference_no : 'No reference' }}
                                        @if ($expense->receipt_url) · <a href="{{ $expense->receipt_url }}" target="_blank" rel="noopener">Receipt</a>@endif
                                    </div>
                                </td>
                                <td>{{ $expense->category?->name ?? '—' }}</td>
                                <td>{{ $expense->expense_date?->format('d M Y') }}</td>
                                <td>{{ ucwords(str_replace('_', ' ', $expense->payment_method)) }}</td>
                                <td class="num" style="font-weight:600">{{ money($expense->amount) }}</td>
                                <td>
                                    <div>{{ $expense->recordedBy?->name ?? 'System' }}</div>
                                    <div class="cell-sub">{{ $expense->created_at?->format('d M Y') }}</div>
                                </td>
                                <td class="actions">
                                    @can('finance.manage')
                                        <a href="{{ route('expenses.edit', $expense) }}" class="btn-icon" title="Edit expense"><x-icon name="pencil" :size="15" /></a>
                                        <form method="POST" action="{{ route('expenses.destroy', $expense) }}" onsubmit="return confirm('Archive this expense? It will be removed from active reporting but retained for audit and can be restored by an administrator.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-icon" title="Archive expense"><x-icon name="archive" :size="15" /></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="table-empty">No expenses match the current filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $expenses->links() }}
        </div>

        <div class="stack">
            <div class="card">
                <div class="card-header"><div><h3 class="card-title">By Category</h3><p class="card-sub">Session totals</p></div></div>
                <div class="card-body">
                    @forelse ($byCategory as $row)
                        <div class="due-row">
                            <div class="due-info">
                                <span class="quick-icon violet" style="width:30px;height:30px"><x-icon name="tag" :size="14" /></span>
                                <div><div class="cell-title">{{ $row->category?->name ?? 'Uncategorised' }}</div></div>
                            </div>
                            <div class="due-amount">{{ money($row->total) }}</div>
                        </div>
                    @empty
                        <x-empty icon="credit-card" title="No expenses" message="Record an expense to see the category breakdown." />
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="info-box">
                        Expenses are soft-deleted. Archived records remain available for audit and reporting history, and financial data is never permanently removed from the ledger.
                    </div>
                </div>
            </div>
        </div>
    </div>

    @can('finance.manage')
        <x-modal name="add-category" title="Add expense category">
            <form method="POST" action="{{ route('expenses.categories.store') }}">
                @csrf
                <div class="stack">
                    <x-input name="name" label="Category name" required placeholder="e.g. Utilities" />
                    <x-button type="submit" variant="primary" icon="plus" class="btn-block">Add category</x-button>
                </div>
            </form>
        </x-modal>
    @endcan
@endsection
