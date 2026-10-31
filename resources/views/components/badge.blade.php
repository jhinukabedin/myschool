@props(['color' => 'muted'])

<span {{ $attributes->merge(['class' => 'badge badge-'.$color]) }}>{{ $slot }}</span>
