<?php

namespace Tests\Unit;

use App\Support\BanglaCalendar;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BanglaCalendarTest extends TestCase
{
    #[DataProvider('conversionProvider')]
    public function test_it_converts_gregorian_dates_to_the_revised_bangla_calendar(
        string $gregorian,
        int $year,
        int $month,
        int $day,
    ): void {
        $this->assertSame(
            ['year' => $year, 'month' => $month, 'day' => $day],
            BanglaCalendar::fromGregorian(CarbonImmutable::parse($gregorian))
        );
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: int, 3: int}>
     */
    public static function conversionProvider(): array
    {
        return [
            'new year starts on 14 April' => ['2026-04-14', 1433, 1, 1],
            'day before new year' => ['2026-04-13', 1432, 12, 30],
            'task example (2 September 2026)' => ['2026-09-02', 1433, 5, 18],
            'poush starts on 16 December' => ['2026-12-16', 1433, 9, 1],
            'first Boishakh' => ['2024-04-14', 1431, 1, 1],
            'last Chaitra' => ['2025-04-13', 1431, 12, 30],
        ];
    }

    #[DataProvider('monthStartProvider')]
    public function test_every_bangla_month_starts_on_the_expected_gregorian_date(
        string $gregorian,
        int $month,
    ): void {
        $date = BanglaCalendar::fromGregorian(CarbonImmutable::parse($gregorian));

        $this->assertSame($month, $date['month'], "Expected {$gregorian} to start month {$month}.");
        $this->assertSame(1, $date['day']);
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function monthStartProvider(): array
    {
        return [
            'Boishakh' => ['2026-04-14', 1],
            'Jyoishtho' => ['2026-05-15', 2],
            'Ashar' => ['2026-06-15', 3],
            'Srabon' => ['2026-07-16', 4],
            'Bhadro' => ['2026-08-16', 5],
            'Ashwin' => ['2026-09-16', 6],
            'Kartik' => ['2026-10-17', 7],
            'Ogrohayon' => ['2026-11-16', 8],
            'Poush' => ['2026-12-16', 9],
            'Magh' => ['2027-01-15', 10],
            'Falgun' => ['2027-02-14', 11],
            'Chaitra' => ['2027-03-15', 12],
        ];
    }

    public function test_falgun_uses_29_or_30_days_following_the_gregorian_leap_year(): void
    {
        // Falgun of the Bangla year starting in 2025 falls in February 2026.
        $this->assertSame(29, BanglaCalendar::monthLengths(2025)[10]);

        // Falgun of the Bangla year starting in 2023 falls in February 2024.
        $this->assertSame(30, BanglaCalendar::monthLengths(2023)[10]);

        // Century rule: 1900 is not a leap year, 2000 is.
        $this->assertSame(29, BanglaCalendar::monthLengths(1899)[10]);
        $this->assertSame(30, BanglaCalendar::monthLengths(1999)[10]);

        $this->assertCount(12, BanglaCalendar::monthLengths(2026));
        $this->assertSame(31, BanglaCalendar::monthLengths(2026)[0]);
        $this->assertSame(30, BanglaCalendar::monthLengths(2026)[9]);
        $this->assertSame(31 * 6 + 30 * 4 + 29 + 30, array_sum(BanglaCalendar::monthLengths(2026)));
        $this->assertSame(366, array_sum(BanglaCalendar::monthLengths(2023)));
    }

    public function test_leap_day_and_the_falgun_chaitra_boundary(): void
    {
        // 1430 is a leap Bangla year: Falgun 1 = 14 Feb 2024, Falgun 30 = 14 Mar 2024.
        $this->assertSame(['year' => 1430, 'month' => 11, 'day' => 16], BanglaCalendar::fromGregorian(CarbonImmutable::parse('2024-02-29')));
        $this->assertSame(['year' => 1430, 'month' => 11, 'day' => 30], BanglaCalendar::fromGregorian(CarbonImmutable::parse('2024-03-14')));
        $this->assertSame(['year' => 1430, 'month' => 12, 'day' => 1], BanglaCalendar::fromGregorian(CarbonImmutable::parse('2024-03-15')));

        // 1429 is not a leap Bangla year: Falgun 29 = 14 Mar 2023.
        $this->assertSame(['year' => 1429, 'month' => 11, 'day' => 15], BanglaCalendar::fromGregorian(CarbonImmutable::parse('2023-02-28')));
        $this->assertSame(['year' => 1429, 'month' => 11, 'day' => 16], BanglaCalendar::fromGregorian(CarbonImmutable::parse('2023-03-01')));
        $this->assertSame(['year' => 1429, 'month' => 11, 'day' => 29], BanglaCalendar::fromGregorian(CarbonImmutable::parse('2023-03-14')));
        $this->assertSame(['year' => 1429, 'month' => 12, 'day' => 1], BanglaCalendar::fromGregorian(CarbonImmutable::parse('2023-03-15')));
    }

    public function test_numeral_and_month_name_helpers(): void
    {
        $this->assertSame('১৮', BanglaCalendar::toBanglaNumerals(18));
        $this->assertSame('১৪৩৩', BanglaCalendar::toBanglaNumerals('1433'));
        $this->assertSame('Falgun', BanglaCalendar::monthName(11, 'english'));
        $this->assertSame('ফাল্গুন', BanglaCalendar::monthName(11));
        $this->assertSame('September', BanglaCalendar::gregorianMonthName(9, 'english'));
        $this->assertSame('সেপ্টেম্বর', BanglaCalendar::gregorianMonthName(9));
    }
}
