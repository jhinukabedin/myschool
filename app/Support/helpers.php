<?php

use App\Models\AcademicSession;
use App\Models\SchoolSetting;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

if (! function_exists('school_setting')) {
    function school_setting(string $key, mixed $default = null): mixed
    {
        try {
            return SchoolSetting::get($key, $default);
        } catch (Throwable) {
            return $default;
        }
    }
}

if (! function_exists('current_session')) {
    function current_session(): ?AcademicSession
    {
        if (App::bound('currentAcademicSession')) {
            return App::make('currentAcademicSession');
        }

        try {
            $session = AcademicSession::currentSession();
        } catch (Throwable) {
            return null;
        }

        if ($session) {
            App::instance('currentAcademicSession', $session);
        }

        return $session;
    }
}

if (! function_exists('money')) {
    function money(mixed $amount, int $decimals = 2): string
    {
        $symbol = school_setting('currency_symbol', config('school.currency', '৳'));

        return $symbol.' '.number_format((float) $amount, $decimals, '.', ',');
    }
}

if (! function_exists('school_name')) {
    function school_name(): string
    {
        return (string) school_setting('school_name', config('app.name'));
    }
}

if (! function_exists('number_to_words')) {
    /**
     * Convert an integer to English words (0 – 999,999,999).
     */
    function number_to_words(int $number): string
    {
        $ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten',
            'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
        $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

        $belowHundred = function (int $n) use ($ones, $tens): string {
            if ($n < 20) {
                return $ones[$n];
            }

            return trim($tens[intdiv($n, 10)].($n % 10 ? ' '.$ones[$n % 10] : ''));
        };

        $belowThousand = function (int $n) use ($ones, $belowHundred): string {
            $parts = [];

            if ($n >= 100) {
                $parts[] = $ones[intdiv($n, 100)].' hundred';
                $n %= 100;
            }

            if ($n > 0) {
                $parts[] = $belowHundred($n);
            }

            return implode(' ', $parts);
        };

        if ($number < 0) {
            return 'minus '.number_to_words(abs($number));
        }

        if ($number === 0) {
            return 'zero';
        }

        $segments = [
            [1000000, 'million'],
            [1000, 'thousand'],
            [1, ''],
        ];

        $parts = [];

        foreach ($segments as [$value, $label]) {
            if ($number >= $value) {
                $chunk = intdiv($number, $value);
                $number %= $value;
                $parts[] = trim($belowThousand($chunk).' '.$label);
            }
        }

        return trim(implode(' ', array_filter($parts)));
    }
}

if (! function_exists('amount_in_words')) {
    /**
     * "Five Thousand Six Hundred Taka and Fifty Paisa Only".
     */
    function amount_in_words(float $amount): string
    {
        $taka = (int) floor(abs($amount));
        $paisa = (int) round((abs($amount) - $taka) * 100);

        $words = ucwords(number_to_words($taka)).' Taka';

        if ($paisa > 0) {
            $words .= ' and '.ucwords(number_to_words($paisa)).' Paisa';
        }

        return $words.' Only';
    }
}

if (! function_exists('user_name')) {
    function user_name(): string
    {
        return Auth::user()?->name ?? 'System';
    }
}

if (! function_exists('normalize_phone')) {
    /**
     * Normalise Bangladeshi mobile numbers to the local 11-digit form.
     * Parent accounts are shared between siblings by this value.
     */
    function normalize_phone(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '880') && strlen($digits) > 10) {
            return '0'.substr($digits, 3);
        }

        if (str_starts_with($digits, '1') && strlen($digits) === 10) {
            return '0'.$digits;
        }

        return $digits;
    }
}

if (! function_exists('bangla_number_enabled')) {
    function bangla_number_enabled(): bool
    {
        if (app()->getLocale() !== 'bn') {
            return false;
        }

        return filter_var(school_setting('bangla_number', '0'), FILTER_VALIDATE_BOOL) ?? false;
    }
}

if (! function_exists('to_bangla_digits')) {
    function to_bangla_digits(mixed $value): string
    {
        $map = ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯'];

        return strtr((string) $value, $map);
    }
}

if (! function_exists('localized_number')) {
    function localized_number(mixed $value, int $decimals = 0): string
    {
        $formatted = number_format((float) $value, $decimals, '.', ',');

        return bangla_number_enabled() ? to_bangla_digits($formatted) : $formatted;
    }
}
if (! function_exists('portal_default_password')) {
    function portal_default_password(): string
    {
        return (string) config('school.default_password', 'School@12345');
    }
}

if (! function_exists('feature_enabled')) {
    /**
     * Whether the current user may open a feature (plan, unlock or super admin).
     */
    function feature_enabled(?string $feature): bool
    {
        return app(\App\Services\ModuleAccess::class)->canAccessFeature(auth()->user(), $feature);
    }
}

if (! function_exists('module_enabled')) {
    /**
     * Whether the current user can use a subscription module.
     */
    function module_enabled(?string $key): bool
    {
        if (! $key) {
            return true;
        }

        if (auth()->user()?->isSuperAdmin()) {
            return true;
        }

        return app(\App\Services\ModuleAccess::class)->enabled($key);
    }
}
