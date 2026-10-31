@props(['href' => null, 'variant' => 'primary', 'size' => null, 'icon' => null, 'type' => 'button'])

@php
    $classes = collect(['btn', 'btn-'.$variant, $size ? 'btn-'.$size : null])->filter()->implode(' ');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" :size="15" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" :size="15" />@endif
        {{ $slot }}
    </button>
@endif
