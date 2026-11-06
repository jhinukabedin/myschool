@extends('layouts.app')

@section('title', $income->exists ? __('Edit Other Income') : __('Record Other Income'))

@section('content')
    <x-page-header eyebrow="{{ __('Finance') }}" :title="$income->exists ? __('Edit Other Income') : __('Record Other Income')" subtitle="{{ __('Record non-fee income such as donations, grants or miscellaneous receipts.') }}">
        <x-slot:actions>
            <x-button :href="route('other-incomes.index')" variant="secondary" icon="arrow-left">{{ __('Back') }}</x-button>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ $income->exists ? route('other-incomes.update', $income) : route('other-incomes.store') }}">
        @csrf
        @if ($income->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-header"><div><h3 class="card-title">{{ __('Income details') }}</h3></div></div>
            <div class="form-section">
                <div class="form-grid">
                    <x-input name="title" :label="__('Title')" :value="old('title', $income->title)" required />
                    <x-select name="other_income_category_id" :label="__('Category')" :options="$categories->pluck('name', 'id')" :selected="old('other_income_category_id', $income->other_income_category_id)" :placeholder="__('Uncategorised')" />
                    <x-input name="income_date" :label="__('Income date')" type="date" :value="old('income_date', $income->income_date?->toDateString() ?? now()->toDateString())" required />
                    <x-input name="reference" :label="__('Reference (optional)')" :value="old('reference', $income->reference)" :placeholder="__('Receipt number, transaction ID...')" />
                    <div class="span-2">
                        <label class="field">
                            <span class="field-label">{{ __('Notes (optional)') }}</span>
                            <textarea class="textarea" name="notes" rows="3" maxlength="1000">{{ old('notes', $income->notes) }}</textarea>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        @php
            $existingItems = $income->exists
                ? $income->items->map(fn ($item) => [
                    'description' => $item->description,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                ])->values()->all()
                : [];

            if ($existingItems === [] && old('items')) {
                $existingItems = collect(old('items'))->map(fn ($item) => [
                    'description' => (string) ($item['description'] ?? ''),
                    'quantity' => (float) ($item['quantity'] ?? 1),
                    'unit_price' => (float) ($item['unit_price'] ?? 0),
                ])->values()->all();
            }

            if ($existingItems === []) {
                $existingItems = [['description' => '', 'quantity' => 1, 'unit_price' => 0]];
            }
        @endphp

        <div class="card" x-data="incomeItems(@js($existingItems))">
            <div class="card-header">
                <div><h3 class="card-title">{{ __('Income Items') }}</h3><p class="card-sub">{{ __('One income entry can hold several receipts. Add a row for each item — amounts calculate automatically.') }}</p></div>
                <button type="button" class="btn btn-secondary btn-sm" @click="add()">
                    <x-icon name="plus" :size="14" /> {{ __('Add item') }}
                </button>
            </div>

            @if ($errors->has('items') || $errors->has('items.*'))
                <div class="form-section" style="padding-bottom:0">
                    <div class="alert alert-warning" role="alert">
                        <span class="alert-icon"><x-icon name="alert-triangle" :size="17" /></span>
                        <div>
                            <strong>{{ __('Please check the item rows') }}</strong>
                            <ul style="margin:6px 0 0;padding-left:18px">
                                @foreach ($errors->get('items') as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                                @foreach ($errors->get('items.*') as $messages)
                                    @foreach ($messages as $message)
                                        <li>{{ $message }}</li>
                                    @endforeach
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:44px">#</th>
                            <th>{{ __('Item / description') }}</th>
                            <th class="num" style="width:110px">{{ __('Qty') }}</th>
                            <th class="num" style="width:150px">{{ __('Unit price') }}</th>
                            <th class="num" style="width:150px">{{ __('Amount') }}</th>
                            <th style="width:56px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(item, index) in items" :key="index">
                            <tr>
                                <td class="muted" x-text="index + 1"></td>
                                <td>
                                    <input class="input" type="text" :name="'items[' + index + '][description]'" x-model="item.description" maxlength="200" placeholder="e.g. Donation from alumni">
                                </td>
                                <td>
                                    <input class="input" type="number" step="0.001" min="0.001" :name="'items[' + index + '][quantity]'" x-model.number="item.quantity">
                                </td>
                                <td>
                                    <input class="input" type="number" step="0.01" min="0" :name="'items[' + index + '][unit_price]'" x-model.number="item.unit_price" placeholder="0.00">
                                </td>
                                <td class="num" style="font-weight:600" x-text="fmt(lineTotal(item))"></td>
                                <td class="actions">
                                    <button type="button" class="btn-icon" title="{{ __('Remove item') }}"
                                            @click="remove(index)" :disabled="items.length <= 1">
                                        <x-icon name="trash" :size="15" />
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" style="text-align:right;font-weight:600">{{ __('Invoice total') }}</td>
                            <td class="num" style="font-weight:700;font-size:15px" x-text="fmt(total)"></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="form-section" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                <p class="field-hint" style="margin:0">{{ __('Saving uses the server-calculated total, so the stored amount always matches the item rows.') }}</p>
                <div style="display:flex;align-items:center;gap:10px">
                    <span class="muted" style="font-size:12px">{{ __('Items') }}: <strong x-text="items.length"></strong></span>
                    <x-input name="amount" :label="__('Total amount')" type="number" step="0.01" readonly x-bind:value="total.toFixed(2)" tabindex="-1" />
                </div>
            </div>
        </div>

        <div class="card">
            <div class="form-section" style="display:flex;justify-content:flex-end;gap:8px">
                <x-button :href="route('other-incomes.index')" variant="ghost">{{ __('Cancel') }}</x-button>
                <x-button type="submit" variant="primary" icon="save">{{ $income->exists ? __('Save') : __('Record income') }}</x-button>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    function incomeItems(initial) {
        const clean = (row) => ({
            description: row?.description ?? '',
            quantity: Number(row?.quantity ?? 1) || 1,
            unit_price: Number(row?.unit_price ?? 0) || 0,
        });

        return {
            items: (Array.isArray(initial) && initial.length ? initial : [{}]).map(clean),

            add() {
                this.items.push({ description: '', quantity: 1, unit_price: 0 });
            },

            remove(index) {
                if (this.items.length <= 1) {
                    this.items[0] = { description: '', quantity: 1, unit_price: 0 };

                    return;
                }

                this.items.splice(index, 1);
            },

            lineTotal(item) {
                const quantity = Math.max(0, Number(item.quantity) || 0);
                const price = Math.max(0, Number(item.unit_price) || 0);

                return Math.round(quantity * price * 100) / 100;
            },

            get total() {
                return Math.round(this.items.reduce((sum, item) => sum + this.lineTotal(item), 0) * 100) / 100;
            },

            fmt(value) {
                return '৳ ' + (Number(value) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
        };
    }
</script>
@endpush
