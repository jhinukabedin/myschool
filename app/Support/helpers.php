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

if (! function_exists('portal_default_password')) {
    function portal_default_password(): string
    {
        return (string) config('school.default_password', 'School@12345');
    }
}
