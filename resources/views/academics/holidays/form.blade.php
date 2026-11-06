@php($editing = $holiday->exists)

<form method="POST" action="{{ $editing ? route('holidays.update', $holiday) : route('holidays.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="stack">
        <x-input name="name" :label="__('Holiday name')" :value="$holiday->name" required placeholder="e.g. Eid-ul-Fitr" />
        <x-select name="type" :label="__('Type')" :options="collect(\App\Models\Holiday::TYPES)->map(fn ($label) => __($label))->all()" :selected="$holiday->type ?? 'public_holiday'" :placeholder="false" required />
        <div class="form-grid">
            <x-input name="start_date" :label="__('Start date')" type="date" :value="$holiday->start_date?->toDateString() ?? now()->toDateString()" required />
            <x-input name="end_date" :label="__('End date')" type="date" :value="$holiday->end_date?->toDateString() ?? now()->toDateString()" required />
        </div>
        <x-select name="applies_to" :label="__('Applies to')" :options="['all' => __('Everyone'), 'students' => __('Students only'), 'staff' => __('Staff only')]" :selected="$holiday->applies_to ?? 'all'" :placeholder="false" required />
        <label class="field">
            <span class="field-label">{{ __('Description (optional)') }}</span>
            <textarea class="textarea" name="description" rows="2" maxlength="1000">{{ old('description', $holiday->description) }}</textarea>
        </label>
        <x-button type="submit" variant="primary" icon="save" class="btn-block">{{ $editing ? __('Save holiday') : __('Add holiday') }}</x-button>
    </div>
</form>
