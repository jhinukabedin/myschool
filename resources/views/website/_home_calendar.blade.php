@php
    $weekdays = ['শনি', 'রবি', 'সোম', 'মঙ্গল', 'বুধ', 'বৃহ', 'শুক্র'];
@endphp

<div class="home-cal">
    <div class="home-cal-nav">
        <a href="{{ $calendar['prev_url'] }}" data-turbo-frame="home-calendar" data-turbo-action="advance" aria-label="আগের মাস">
            <x-icon name="chevron-left" :size="15" />
        </a>
        <span>{{ $calendar['title'] }}</span>
        <a href="{{ $calendar['next_url'] }}" data-turbo-frame="home-calendar" data-turbo-action="advance" aria-label="পরের মাস">
            <x-icon name="chevron-right" :size="15" />
        </a>
    </div>

    <div class="home-cal-weekdays" aria-hidden="true">
        @foreach ($weekdays as $weekday)
            <span>{{ $weekday }}</span>
        @endforeach
    </div>

    <div class="home-cal-days">
        @foreach ($calendar['days'] as $day)
            @if ($day === null)
                <span class="home-cal-day is-blank" aria-hidden="true"></span>
            @else
                <span @class([
                    'home-cal-day',
                    'is-weekly-off' => $day['is_weekly_off'],
                    'is-holiday' => $day['holiday_names'] !== [],
                    'is-today' => $day['is_today'],
                ])
                    @if ($day['holiday_names'] !== []) title="{{ implode(', ', $day['holiday_names']) }}" @endif>
                    {{ to_bangla_digits($day['day']) }}
                </span>
            @endif
        @endforeach
    </div>

    <div class="home-cal-foot">
        <b><span class="home-cal-info">i</span> ছুটির তালিকা:</b>

        @forelse ($calendar['holidays'] as $holiday)
            <div class="home-cal-holiday">
                <span class="home-cal-chip">{{ $holiday['date_label'] }}</span>
                <span>{{ $holiday['name'] }}</span>
            </div>
        @empty
            <p class="home-cal-empty">এই মাসে কোনো ছুটি নেই।</p>
        @endforelse
    </div>
</div>
