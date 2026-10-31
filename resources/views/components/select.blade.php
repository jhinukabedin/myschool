@props([
    'name',
    'label' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => 'Select an option',
    'help' => null,
    'required' => false,
    'multiple' => false,
])

@php
    $current = old($name, $selected);
    if ($multiple && ! is_array($current)) {
        $current = $current ? [$current] : [];
    }
@endphp

<label class="field" for="{{ $name }}">
    @if ($label)
        <span class="field-label">
            {{ $label }}
            @if ($required)<span class="required-mark">*</span>@endif
        </span>
    @endif

    <select
        id="{{ $name }}"
        name="{{ $name }}"
        @if ($required) required @endif
        @if ($multiple) multiple @endif
        {{ $attributes->merge(['class' => 'select'.($errors->has($name) ? ' input-error' : '')]) }}
    >
        @if (! $multiple && $placeholder !== false)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $text)
            @php
                $isSelected = $multiple
                    ? in_array((string) $value, array_map('strval', $current ?? []), true)
                    : (string) $current === (string) $value;
            @endphp
            <option value="{{ $value }}" @selected($isSelected)>{{ $text }}</option>
        @endforeach
    </select>

    @error($name)
        <span class="field-error"><x-icon name="alert-circle" :size="11" />{{ $message }}</span>
    @enderror
    @if ($help)
        <span class="field-hint">{{ $help }}</span>
    @endif
</label>
