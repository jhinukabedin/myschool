<?php

namespace App\Services;

use App\Settings\DateCalendarSettings;
use App\Support\BanglaCalendar;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Formats the current (or a supplied) moment using the configured Bangla and
 * Gregorian calendars.
 *
 * The formatted string is memoised for the current request keyed by the
 * settings and the calendar date, so it is recalculated after midnight while
 * repeated component renders stay cheap.
 */
class DateCalendarService
{
    private const WEEKDAYS_BANGLA = [
        'রবিবার',
        'সোমবার',
        'মঙ্গলবার',
        'বুধবার',
        'বৃহস্পতিবার',
        'শুক্রবার',
        'শনিবার',
    ];

    private const WEEKDAYS_ENGLISH = [
        'Sunday',
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
    ];

    /** @var array<string, array{weekday: ?string, date: string}> */
    private array $parts = [];

    public function settings(): DateCalendarSettings
    {
        // Reading the settings on each call keeps a saved change visible
        // immediately; the underlying key/value store has its own cache.
        return DateCalendarSettings::fromStore();
    }

    /**
     * Return the formatted date, or null when the display is disabled.
     *
     * @param  DateTimeInterface|string|null  $date  Null means "now" in the app timezone.
     */
    public function format(DateTimeInterface|string|null $date = null): ?string
    {
        $parts = $this->parts($date);

        if ($parts === null) {
            return null;
        }

        return $this->join($parts);
    }

    /**
     * Return the weekday and date separately so views can adapt the weekday
     * on narrow screens without recalculating anything.
     *
     * @return array{weekday: ?string, date: string}|null
     */
    public function parts(DateTimeInterface|string|null $date = null): ?array
    {
        $settings = $this->settings();

        if (! $settings->enabled) {
            return null;
        }

        $moment = $this->toMoment($date);
        $key = implode('|', [
            $settings->calendar,
            $settings->language,
            (int) $settings->showWeekday,
            (int) $settings->showBangla,
            (int) $settings->showBanglaYear,
            (int) $settings->showEnglish,
            (int) $settings->showEnglishYear,
            $settings->separator,
            $moment->format('Y-m-d'),
        ]);

        return $this->parts[$key] ??= $this->buildParts($moment, $settings);
    }

    /**
     * @param  array{weekday: ?string, date: string}  $parts
     */
    public function join(array $parts): string
    {
        if ($parts['weekday'] === null) {
            return $parts['date'];
        }

        return $parts['date'] === '' ? $parts['weekday'] : $parts['weekday'].', '.$parts['date'];
    }

    private function toMoment(DateTimeInterface|string|null $date): CarbonImmutable
    {
        $timezone = (string) (config('app.timezone') ?: date_default_timezone_get());

        if ($date === null) {
            return CarbonImmutable::now($timezone);
        }

        if (is_string($date)) {
            return CarbonImmutable::parse($date, $timezone);
        }

        return CarbonImmutable::instance($date)->setTimezone($timezone);
    }

    /**
     * @return array{weekday: ?string, date: string}
     */
    private function buildParts(CarbonImmutable $moment, DateCalendarSettings $settings): array
    {
        $isBangla = $settings->language === 'bangla';
        $parts = [];

        if ($this->shouldShowBangla($settings)) {
            $parts[] = $this->formatBanglaDate($moment, $settings, $isBangla);
        }

        if ($this->shouldShowEnglish($settings)) {
            $parts[] = $this->formatEnglishDate($moment, $settings, $isBangla);
        }

        $weekday = null;

        if ($settings->showWeekday) {
            $weekday = $isBangla
                ? self::WEEKDAYS_BANGLA[(int) $moment->format('w')]
                : self::WEEKDAYS_ENGLISH[(int) $moment->format('w')];
        }

        return [
            'weekday' => $weekday,
            'date' => implode($settings->separator, $parts),
        ];
    }

    private function shouldShowBangla(DateCalendarSettings $settings): bool
    {
        return match ($settings->calendar) {
            DateCalendarSettings::CALENDAR_ENGLISH => false,
            DateCalendarSettings::CALENDAR_BOTH => $settings->showBangla,
            default => true,
        };
    }

    private function shouldShowEnglish(DateCalendarSettings $settings): bool
    {
        return match ($settings->calendar) {
            DateCalendarSettings::CALENDAR_BANGLA => false,
            DateCalendarSettings::CALENDAR_BOTH => $settings->showEnglish,
            default => true,
        };
    }

    private function formatBanglaDate(CarbonImmutable $moment, DateCalendarSettings $settings, bool $isBangla): string
    {
        $date = BanglaCalendar::fromGregorian($moment);

        $text = ($isBangla ? BanglaCalendar::toBanglaNumerals($date['day']) : $date['day'])
            .' '
            .BanglaCalendar::monthName($date['month'], $isBangla ? 'bangla' : 'english');

        if ($settings->showBanglaYear) {
            $text .= ' '
                .($isBangla ? BanglaCalendar::toBanglaNumerals($date['year']) : $date['year'])
                .($isBangla ? ' বঙ্গাব্দ' : ' Bangabda');
        }

        return $text;
    }

    private function formatEnglishDate(CarbonImmutable $moment, DateCalendarSettings $settings, bool $isBangla): string
    {
        $day = $moment->format('j');
        $monthNumber = (int) $moment->format('n');
        $year = $moment->format('Y');

        $text = ($isBangla ? BanglaCalendar::toBanglaNumerals($day) : $day)
            .' '
            .BanglaCalendar::gregorianMonthName($monthNumber, $isBangla ? 'bangla' : 'english');

        if ($settings->showEnglishYear) {
            $text .= ' '.($isBangla ? BanglaCalendar::toBanglaNumerals($year) : $year);
        }

        return $text;
    }
}
