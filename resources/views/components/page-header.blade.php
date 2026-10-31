@props(['title', 'subtitle' => null, 'eyebrow' => null])

<div class="page-header">
    <div>
        @if ($eyebrow)
            <div class="eyebrow muted-2" style="font-size:10px;font-weight:700;letter-spacing:1.4px;text-transform:uppercase;margin-bottom:4px">{{ $eyebrow }}</div>
        @endif
        <h1 class="page-title">{{ $title }}</h1>
        @if ($subtitle)<p class="page-subtitle">{{ $subtitle }}</p>@endif
    </div>
    @isset($actions)
        <div class="page-actions">{{ $actions }}</div>
    @endisset
</div>
