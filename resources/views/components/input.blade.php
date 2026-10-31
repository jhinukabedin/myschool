@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'help' => null, 'required' => false, 'placeholder' => null])

<label class="field" for="{{ $name }}">
    @if ($label)
        <span class="field-label">
            {{ $label }}
            @if ($required)<span class="required-mark">*</span>@endif
        </span>
    @endif

    @if ($type === 'file')
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="file"
            @if ($required) required @endif
            {{ $attributes->merge(['class' => 'input'.($errors->has($name) ? ' input-error' : '')]) }}
        >
    @else
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ old($name, $value) }}"
            @if ($required) required @endif
            placeholder="{{ $placeholder }}"
            {{ $attributes->merge(['class' => 'input'.($errors->has($name) ? ' input-error' : '')]) }}
        >
    @endif

    @error($name)
        <span class="field-error"><x-icon name="alert-circle" :size="11" />{{ $message }}</span>
    @enderror
    @if ($help)
        <span class="field-hint">{{ $help }}</span>
    @endif
</label>
