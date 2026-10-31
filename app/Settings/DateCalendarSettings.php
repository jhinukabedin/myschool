<?php

namespace App\Settings;

/**
 * Immutable snapshot of the "Date & Calendar Settings" stored through the
 * existing school settings key/value store.
 *
 * Missing values fall back to the documented defaults so an application
 * upgraded from an older release keeps working without a migration.
 */
final class DateCalendarSettings
{
    public const CALENDAR_BANGLA = 'bangla';

    public const CALENDAR_ENGLISH = 'english';

    public const CALENDAR_BOTH = 'both';

    public const CALENDARS = [
        self::CALENDAR_BANGLA,
        self::CALENDAR_ENGLISH,
        self::CALENDAR_BOTH,
    ];

    public const LANGUAGES = ['bangla', 'english'];

    public const DEFAULT_SEPARATOR = ' | ';

    public function __construct(
        public readonly bool $enabled = true,
        public readonly string $calendar = self::CALENDAR_BANGLA,
        public readonly string $language = 'bangla',
        public readonly bool $showWeekday = true,
        public readonly bool $showBangla = true,
        public readonly bool $showBanglaYear = true,
        public readonly bool $showEnglish = true,
        public readonly bool $showEnglishYear = true,
        public readonly string $separator = self::DEFAULT_SEPARATOR,
    ) {}

    public static function fromStore(): self
    {
        $calendar = strtolower(trim((string) school_setting('date_calendar', self::CALENDAR_BANGLA)));

        if (! in_array($calendar, self::CALENDARS, true)) {
            $calendar = self::CALENDAR_BANGLA;
        }

        $language = strtolower(trim((string) school_setting('date_language', 'bangla')));

        if (! in_array($language, self::LANGUAGES, true)) {
            $language = 'bangla';
        }

        $separator = school_setting('date_separator');

        return new self(
            enabled: self::boolean('enable_date_display'),
            calendar: $calendar,
            language: $language,
            showWeekday: self::boolean('date_show_weekday'),
            showBangla: self::boolean('date_show_bangla'),
            showBanglaYear: self::boolean('date_show_bangla_year'),
            showEnglish: self::boolean('date_show_english'),
            showEnglishYear: self::boolean('date_show_english_year'),
            separator: $separator === null ? self::DEFAULT_SEPARATOR : (string) $separator,
        );
    }

    /**
     * Parse a stored boolean. Values written by older releases may be absent
     * (null) or an empty string, in which case the documented default applies.
     */
    private static function boolean(string $key, bool $default = true): bool
    {
        $value = school_setting($key);

        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
