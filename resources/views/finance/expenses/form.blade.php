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
                    <x-input name="amount" label="Amount" type="number" step="0.01" min="0.01" :value="$expense->amount" required />
                    <x-input name="expense_date" label="Expense date" type="date" :value="$expense->expense_date?->toDateString()" required />
                    <x-select name="payment_method" label="Payment method" :options="['cash' => 'Cash', 'bank' => 'Bank transfer', 'mobile_banking' => 'Mobile banking', 'cheque' => 'Cheque', 'card' => 'Card']" :selected="$expense->payment_method" required />
                    <x-input name="reference_no" label="Reference no" :value="$expense->reference_no" placeholder="Optional transaction reference" />
                    <div class="span-2"><x-textarea name="description" label="Description" :value="$expense->description" rows="3" placeholder="What was purchased and why?" /></div>
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
