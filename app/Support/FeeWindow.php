<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Defines the default fee billing window.
 *
 * Past-due months are always payable. On top of that we expose the current
 * month plus a small configurable number of future months, so a student's
 * outstanding balance is not inflated by invoices that are not due yet.
 *
 * Users with fee-collection permission can opt into "advance payment" mode,
 * which lifts the window and reveals every remaining invoice of the session.
 */
class FeeWindow
{
    public static function aheadMonths(): int
    {
        return max(0, (int) config('school.fee_window_months_ahead', 1));
    }

    /**
     * Last date (inclusive) that counts as a visible/payable due by default.
     */
    public static function horizon(): CarbonInterface
    {
        return now()
            ->startOfMonth()
            ->addMonthsNoOverflow(self::aheadMonths())
            ->endOfMonth()
            ->startOfDay();
    }

    public static function horizonDate(): string
    {
        return self::horizon()->toDateString();
    }

    /**
     * First month that is still hidden by the default window.
     */
    public static function firstHiddenMonth(): CarbonInterface
    {
        return now()
            ->startOfMonth()
            ->addMonthsNoOverflow(self::aheadMonths() + 1)
            ->startOfDay();
    }

    /**
     * Human label for the window, e.g. "Oct 2026 – Dec 2026".
     */
    public static function label(): string
    {
        $start = now()->startOfMonth();

        return $start->format('M Y').' – '.self::horizon()->format('M Y');
    }

    /**
     * Whether a due date falls inside the default window.
     */
    public static function includes(?CarbonInterface $dueDate): bool
    {
        if (! $dueDate) {
            return true;
        }

        return Carbon::parse($dueDate)->startOfDay()->lessThanOrEqualTo(self::horizon());
    }
}
