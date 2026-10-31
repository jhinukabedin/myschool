<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Converts Gregorian dates to the revised Bangladeshi (Bangla Academy) calendar.
 *
 * Rules effective from 2019 (Bangla year 1426):
 *  - Boishakh through Ashwin (months 1-6) have 31 days.
 *  - Kartik through Magh (months 7-10) have 30 days.
 *  - Falgun has 29 days, or 30 when February of the following Gregorian year
 *    is a leap February.
 *  - Chaitra always has 30 days.
 *
 * The Bangla year starts on 14 April, so 1 Boishakh always maps to 14 April
 * and 1 Chaitra always maps to 15 March.
 */
final class BanglaCalendar
{
    public const MONTHS_BANGLA = [
        'বৈশাখ',
        'জ্যৈষ্ঠ',
        'আষাঢ়',
        'শ্রাবণ',
        'ভাদ্র',
        'আশ্বিন',
        'কার্তিক',
        'অগ্রহায়ণ',
        'পৌষ',
        'মাঘ',
        'ফাল্গুন',
        'চৈত্র',
    ];

    public const MONTHS_ENGLISH = [
        'Boishakh',
        'Jyoishtho',
        'Ashar',
        'Srabon',
        'Bhadro',
        'Ashwin',
        'Kartik',
        'Ogrohayon',
        'Poush',
        'Magh',
        'Falgun',
        'Chaitra',
    ];

    public const GREGORIAN_MONTHS_BANGLA = [
        'জানুয়ারি',
        'ফেব্রুয়ারি',
        'মার্চ',
        'এপ্রিল',
        'মে',
        'জুন',
        'জুলাই',
        'আগস্ট',
        'সেপ্টেম্বর',
        'অক্টোবর',
        'নভেম্বর',
        'ডিসেম্বর',
    ];

    public const GREGORIAN_MONTHS_ENGLISH = [
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December',
    ];

    private const BANGLA_OFFSET = 593;

    /**
     * Convert a Gregorian date to a Bangla calendar date.
     *
     * @return array{year: int, month: int, day: int}
     */
    public static function fromGregorian(DateTimeInterface $date): array
    {
        $gregorianYear = (int) $date->format('Y');
        $gregorianMonth = (int) $date->format('n');
        $gregorianDay = (int) $date->format('j');

        // The Bangla year begins on 14 April.
        $startYear = ($gregorianMonth > 4 || ($gregorianMonth === 4 && $gregorianDay >= 14))
            ? $gregorianYear
            : $gregorianYear - 1;

        $start = new DateTimeImmutable(sprintf('%04d-04-14', $startYear));
        $current = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $gregorianYear, $gregorianMonth, $gregorianDay));

        $offset = (int) $start->diff($current)->days;

        $month = 12;
        foreach (self::monthLengths($startYear) as $index => $length) {
            if ($offset < $length) {
                $month = $index + 1;
                break;
            }

            $offset -= $length;
        }

        return [
            'year' => $startYear - self::BANGLA_OFFSET,
            'month' => $month,
            'day' => $offset + 1,
        ];
    }

    /**
     * Length of every Bangla month for the year that starts in the given
     * Gregorian year. Falgun depends on the leap February inside that year.
     *
     * @return array<int, int>
     */
    public static function monthLengths(int $gregorianStartYear): array
    {
        return [
            31, 31, 31, 31, 31, 31,
            30, 30, 30, 30,
            self::isLeapYear($gregorianStartYear + 1) ? 30 : 29,
            30,
        ];
    }

    public static function isLeapYear(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0;
    }

    public static function monthName(int $month, string $language = 'bangla'): string
    {
        $names = $language === 'english' ? self::MONTHS_ENGLISH : self::MONTHS_BANGLA;

        return $names[$month - 1] ?? '';
    }

    public static function gregorianMonthName(int $month, string $language = 'bangla'): string
    {
        $names = $language === 'english' ? self::GREGORIAN_MONTHS_ENGLISH : self::GREGORIAN_MONTHS_BANGLA;

        return $names[$month - 1] ?? '';
    }

    public static function toBanglaNumerals(string|int $value): string
    {
        return strtr((string) $value, [
            '0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪',
            '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯',
        ]);
    }
}
