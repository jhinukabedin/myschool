@props([
    'date' => null,
    'icon' => false,
])

@inject('dateCalendar', 'App\Services\DateCalendarService')

@php
    $dateParts = $dateCalendar->parts($date);
    $dateSettings = $dateCalendar->settings();
    $formatted = $dateParts === null ? null : $dateCalendar->join($dateParts);
@endphp

@if (filled($formatted))
    <span
        {{ $attributes->class([
            'date-display',
            'date-display-bangla' => $dateSettings->language === 'bangla',
        ])->merge([
            'lang' => $dateSettings->language === 'bangla' ? 'bn' : 'en',
            'title' => $formatted,
        ]) }}
    >
        @if ($icon)
            <x-icon name="calendar" :size="13" class="date-display-icon" />
        @endif
        <span class="date-display-text">@if ($dateParts['weekday'] !== null)<span class="date-display-weekday">{{ $dateParts['weekday'] }}, </span>@endif{{ $dateParts['date'] }}</span>
    </span>
@endif
