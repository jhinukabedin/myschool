@props(['title' => null, 'subtitle' => null, 'padding' => true])

<div {{ $attributes->merge(['class' => 'card']) }}>
    @if ($title || isset($actions))
        <div class="card-header">
            <div>
                @if ($title)<h3 class="card-title">{{ $title }}</h3>@endif
                @if ($subtitle)<p class="card-sub">{{ $subtitle }}</p>@endif
            </div>
            @isset($actions)
                <div class="page-actions">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    @if ($padding)
        <div class="card-body">{{ $slot }}</div>
    @else
        {{ $slot }}
    @endif
</div>
