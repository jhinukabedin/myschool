@props(['name', 'label' => null, 'value' => null, 'help' => null, 'required' => false, 'rows' => 4, 'placeholder' => null])

<label class="field" for="{{ $name }}">
    @if ($label)
        <span class="field-label">
            {{ $label }}
            @if ($required)<span class="required-mark">*</span>@endif
        </span>
    @endif

    <textarea
        id="{{ $name }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @if ($required) required @endif
        placeholder="{{ $placeholder }}"
        {{ $attributes->merge(['class' => 'textarea'.($errors->has($name) ? ' input-error' : '')]) }}
    >{{ old($name, $value) }}</textarea>

    @error($name)
        <span class="field-error"><x-icon name="alert-circle" :size="11" />{{ $message }}</span>
    @enderror
    @if ($help)
        <span class="field-hint">{{ $help }}</span>
    @endif
</label>
