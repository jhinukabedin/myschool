@extends('layouts.app')

@section('title', $structure->exists ? 'Edit Fee Structure' : 'New Fee Structure')

@php($rows = old('items', $initialItems))

@section('content')
    <x-page-header
        eyebrow="Fees"
        :title="$structure->exists ? 'Edit Fee Structure' : 'New Fee Structure'"
        subtitle="Fees are assigned per student and period. Monthly items create one due row for each month in the structure’s effective range."
    >
        <x-slot:actions>
            <x-button :href="route('fees.structures.index')" variant="secondary" icon="arrow-left">Fee structures</x-button>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ $structure->exists ? route('fees.structures.update', $structure) : route('fees.structures.store') }}" x-data="{ items: {{ Js::from($rows) }} }" x-init="if (!items.length) items.push({ fee_category_id: '', amount: '', discount_amount: 0, frequency: 'monthly', due_day: 10, is_optional: false })">
        @csrf
        @if ($structure->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Structure Details</h3><p class="card-sub">Session and class must match when assigning</p></div></div>
            <div class="form-section">
                <div class="form-grid">
                    <x-input name="name" label="Structure name" :value="$structure->name" required placeholder="e.g. Class 6 Standard Fees" />
                    <x-select name="academic_session_id" label="Academic session" :options="$sessions->pluck('name', 'id')" :selected="$structure->academic_session_id" required />
                    <x-select name="class_id" label="Class" :options="$classes->pluck('name', 'id')" :selected="$structure->class_id" required />
                    <x-select name="status" label="Status" :options="['active' => 'Active', 'inactive' => 'Inactive']" :selected="$structure->status" required />
                    <x-input name="effective_from" label="Effective from" type="date" :value="$structure->effective_from?->toDateString()" required />
                    <x-input name="effective_to" label="Effective to" type="date" :value="$structure->effective_to?->toDateString()" help="Blank means the full session." />
                    <div class="span-2"><x-textarea name="description" label="Description" :value="$structure->description" rows="2" /></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div><h3 class="card-title">Fee Items</h3><p class="card-sub">Each fee category may appear once</p></div>
                <button type="button" class="btn btn-secondary btn-sm" @click="items.push({ fee_category_id: '', amount: '', discount_amount: 0, frequency: 'monthly', due_day: 10, is_optional: false })">
                    <x-icon name="plus" :size="14" /> Add item
                </button>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th style="min-width:190px">Fee category</th><th>Amount</th><th>Discount</th><th>Frequency</th><th>Due day</th><th>Optional</th><th></th></tr></thead>
                    <tbody>
                        <template x-for="(item, index) in items" :key="index">
                            <tr>
                                <td>
                                    <select class="select" x-model="item.fee_category_id" :name="`items[${index}][fee_category_id]`" required>
                                        <option value="">Select category</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input class="input" type="number" min="0" step="0.01" x-model="item.amount" :name="`items[${index}][amount]`" required></td>
                                <td><input class="input" type="number" min="0" step="0.01" x-model="item.discount_amount" :name="`items[${index}][discount_amount]`"></td>
                                <td>
                                    <select class="select" x-model="item.frequency" :name="`items[${index}][frequency]`" required>
                                        <option value="monthly">Monthly</option>
                                        <option value="one_time">One-time</option>
                                        <option value="annual">Annual</option>
                                        <option value="exam">Exam</option>
                                    </select>
                                </td>
                                <td><input class="input" type="number" min="1" max="28" x-model="item.due_day" :name="`items[${index}][due_day]`" required></td>
                                <td><input type="checkbox" x-model="item.is_optional" :name="`items[${index}][is_optional]`" value="1"></td>
                                <td class="actions"><button type="button" class="btn-icon" @click="items.splice(index, 1)" :disabled="items.length === 1" title="Remove item"><x-icon name="trash" :size="15" /></button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="form-section" style="display:flex;justify-content:flex-end;gap:8px">
                <x-button :href="route('fees.structures.index')" variant="ghost">Cancel</x-button>
                <x-button type="submit" variant="primary" icon="save">{{ $structure->exists ? 'Save structure' : 'Create structure' }}</x-button>
            </div>
        </div>
    </form>
@endsection
