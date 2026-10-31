@php
    $links = $links ?? [];
    $class = $class ?? 'footer-social';
@endphp

@if (! empty($links))
    <div class="{{ $class }}">
        @foreach ($links as $link)
            <a href="{{ $link['url'] }}"
                @if (! \Illuminate\Support\Str::startsWith($link['url'], ['mailto:', 'tel:'])) target="_blank" rel="noopener" @endif
                title="{{ $link['label'] }}"
                aria-label="{{ $link['label'] }}"
                style="background:{{ $link['color'] }};border-color:{{ $link['color'] }}">
                <span>{{ $link['glyph'] }}</span>
            </a>
        @endforeach
    </div>
@endif
