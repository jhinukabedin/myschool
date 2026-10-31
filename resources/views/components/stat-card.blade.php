@props(['label', 'value', 'icon' => 'users', 'tone' => 'student', 'meta' => null, 'trend' => null, 'direction' => 'flat'])

<div {{ $attributes->merge(['class' => 'stat-card '.$tone]) }}>
    <div class="stat-top">
        <span class="stat-label">{{ $label }}</span>
        <span class="stat-icon"><x-icon :name="$icon" :size="19" /></span>
    </div>
    <div>
        <div class="stat-value">{{ $value }}</div>
        @if ($meta || $trend)
            <div class="stat-meta">
                @if ($trend)
                    <span class="trend {{ $direction }}">
                        <x-icon :name="$direction === 'up' ? 'trending-up' : ($direction === 'down' ? 'trending-down' : 'activity')" :size="11" />
                        {{ $trend }}
                    </span>
                @endif
                @if ($meta)<span>{{ $meta }}</span>@endif
            </div>
        @endif
    </div>
</div>
