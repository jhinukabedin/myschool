@extends('layouts.app')

@section('title', $expense->exists ? 'Edit Expense' : 'Record Expense')

@section('content')
    <x-page-header eyebrow="Finance" :title="$expense->exists ? 'Edit Expense' : 'Record Expense'" subtitle="Expenses are part of the financial ledger. Record the receipt or reference so the entry can be audited.">
        <x-slot:actions>
            <x-button :href="route('expenses.index')" variant="secondary" icon="arrow-left">Expenses</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($categories->isEmpty())
        <div class="alert alert-warning" role="alert">
            <span class="alert-icon"><x-icon name="alert-triangle" :size="17" /></span>
            <div>Create an expense category before recording an expense. You can add one from the expenses page.</div>
        </div>
    @endif

    <form method="POST" action="{{ $expense->exists ? route('expenses.update', $expense) : route('expenses.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($expense->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Expense Details</h3><p class="card-sub">All amounts are stored as decimals</p></div></div>
            <div class="form-section">
                <div class="form-grid">
                    <div class="span-2"><x-input name="title" label="Expense title" :value="$expense->title" required placeholder="e.g. October electricity bill" /></div>
                    <x-select name="expense_category_id" label="Category" :options="$categories->pluck('name', 'id')" :selected="$expense->expense_category_id" required placeholder="Select a category" />
                    <x-select name="academic_session_id" label="Academic session" :options="$sessions->pluck('name', 'id')" :selected="$expense->academic_session_id" required />
                    <x-input name="expense_date" label="Expense date" type="date" :value="$expense->expense_date?->toDateString()" required />
                    <x-select name="payment_method" label="Payment method" :options="['cash' => 'Cash', 'bank' => 'Bank transfer', 'mobile_banking' => 'Mobile banking', 'cheque' => 'Cheque', 'card' => 'Card']" :selected="$expense->payment_method" required />
                    <x-input name="reference_no" label="Reference no" :value="$expense->reference_no" placeholder="Optional transaction reference" />
                    <div class="span-2"><x-textarea name="description" label="Description" :value="$expense->description" rows="3" placeholder="What was purchased and why?" /></div>
                </div>
            </div>
        </div>

        @php
            $existingItems = $expense->exists
                ? $expense->items->map(fn ($item) => [
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

        <div class="card" x-data="expenseItems(@js($existingItems))">
            <div class="card-header">
                <div><h3 class="card-title">Invoice Items</h3><p class="card-sub">One expense can hold several purchases. Add a row for each item — amounts calculate automatically.</p></div>
                <button type="button" class="btn btn-secondary btn-sm" @click="add()">
                    <x-icon name="plus" :size="14" /> Add item
                </button>
            </div>

            @if ($errors->has('items') || $errors->has('items.*'))
                <div class="form-section" style="padding-bottom:0">
                    <div class="alert alert-warning" role="alert">
                        <span class="alert-icon"><x-icon name="alert-triangle" :size="17" /></span>
                        <div>
                            <strong>Please check the item rows</strong>
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
                            <th>Item / description</th>
                            <th class="num" style="width:110px">Qty</th>
                            <th class="num" style="width:150px">Unit price</th>
                            <th class="num" style="width:150px">Amount</th>
                            <th style="width:56px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(item, index) in items" :key="index">
                            <tr>
                                <td class="muted" x-text="index + 1"></td>
                                <td>
                                    <input class="input" type="text" :name="'items[' + index + '][description]'" x-model="item.description" maxlength="200" placeholder="e.g. A4 paper ream">
                                </td>
                                <td>
                                    <input class="input" type="number" step="0.001" min="0.001" :name="'items[' + index + '][quantity]'" x-model.number="item.quantity">
                                </td>
                                <td>
                                    <input class="input" type="number" step="0.01" min="0" :name="'items[' + index + '][unit_price]'" x-model.number="item.unit_price" placeholder="0.00">
                                </td>
                                <td class="num" style="font-weight:600" x-text="fmt(lineTotal(item))"></td>
                                <td class="actions">
                                    <button type="button" class="btn-icon" title="Remove item"
                                            @click="remove(index)" :disabled="items.length <= 1">
                                        <x-icon name="trash" :size="15" />
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" style="text-align:right;font-weight:600">Invoice total</td>
                            <td class="num" style="font-weight:700;font-size:15px" x-text="fmt(total)"></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="form-section" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                <p class="field-hint" style="margin:0">Saving uses the server-calculated total, so the stored amount always matches the item rows.</p>
                <div style="display:flex;align-items:center;gap:10px">
                    <span class="muted" style="font-size:12px">Items: <strong x-text="items.length"></strong></span>
                    <x-input name="amount" label="Total amount" type="number" step="0.01" readonly x-bind:value="total.toFixed(2)" tabindex="-1" />
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Supporting Document</h3><p class="card-sub">Optional receipt image or PDF, up to 4 MB</p></div></div>
            <div class="form-section">
                <div class="form-grid">
                    <div class="span-2">
                        <label class="field" for="receipt">
                            <span class="field-label">Receipt</span>
                            <input id="receipt" class="input" type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf">
                            @if ($expense->receipt_path)
                                <span class="field-hint">
                                    A receipt is already stored.
                                    <a href="{{ $expense->receipt_url }}" target="_blank" rel="noopener">View current receipt</a>.
                                    Uploading a new file replaces it.
                                </span>
                            @else
                                <span class="field-hint">Attach a scanned bill or receipt if available.</span>
                            @endif
                            @error('receipt')<span class="field-error">{{ $message }}</span>@enderror
                        </label>
                    </div>
                </div>
                <div class="info-box" style="margin-top:14px">
                    @if ($expense->exists)
                        Editing this entry is recorded in the audit log with the previous and new values. To remove it from active reporting, archive it from the expenses list instead of editing it away.
                    @else
                        The expense will be linked to {{ current_session()?->name ?? 'the selected academic session' }} and the user who records it.
                    @endif
                </div>
            </div>
        </div>

        <div class="card">
            <div class="form-section" style="display:flex;justify-content:flex-end;gap:8px">
                <x-button :href="route('expenses.index')" variant="ghost">Cancel</x-button>
                @if ($categories->isNotEmpty())
                    <x-button type="submit" variant="primary" icon="save">{{ $expense->exists ? 'Save expense' : 'Record expense' }}</x-button>
                @endif
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    function expenseItems(initial) {
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
