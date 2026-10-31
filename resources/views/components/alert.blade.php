@props(['type' => 'info', 'title' => null, 'dismissible' => false])

@php
    $icons = ['success' => 'check-circle', 'danger' => 'alert-circle', 'warning' => 'alert-triangle', 'info' => 'info'];
@endphp

<div {{ $attributes->merge(['class' => 'alert alert-'.$type]) }} role="alert">
    <span class="alert-icon"><x-icon :name="$icons[$type] ?? 'info'" :size="17" /></span>
    <div style="flex:1">
        @if ($title)<div class="alert-title">{{ $title }}</div>@endif
        {{ $slot }}
    </div>
</div>
