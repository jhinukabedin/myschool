<?php

/**
 * Verifies the redesigned homepage: two-column upcoming events on the left and
 * an admin-managed academic calendar on the right, with month navigation.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Holiday;

const BASE = 'http://newschool.test';

$passed = 0;
$failed = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  [OK  ] $label\n";
    } else {
        $failed++;
        echo "  [FAIL] $label".($detail !== '' ? " ($detail)" : '')."\n";
    }
}

function req(string $path): array
{
    $ch = curl_init(BASE.$path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => ['Accept: text/html'],
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => (string) $body];
}

function countTags(string $html, string $selector): int
{
    // Tiny helper for tag counts via regex; enough for our controlled markup.
    return preg_match_all('/'.preg_quote($selector, '/').'/i', $html);
}

// Make sure test holidays exist.
$expected = [
    ['name' => 'শহীদ দিবস (ভিক্টোরি ডে)', 'start_date' => '2026-11-16', 'end_date' => '2026-11-16', 'type' => 'public_holiday', 'applies_to' => 'all'],
    ['name' => 'শীতকালীন অবকাশ', 'start_date' => '2026-12-25', 'end_date' => '2026-12-31', 'type' => 'vacation', 'applies_to' => 'students'],
];
foreach ($expected as $row) {
    Holiday::firstOrCreate(
        ['name' => $row['name'], 'start_date' => $row['start_date']],
        $row + ['description' => 'QA holiday', 'created_by' => 1]
    );
}

// Current homepage
$home = req('/');
check('homepage loads', $home['status'] === 200, (string) $home['status']);
check('homepage has the two-column events section', str_contains($home['body'], 'home-events-layout'));
check('upcoming events panel is present', str_contains($home['body'], 'আসন্ন অনুষ্ঠান'));
check('events list is two columns', str_contains($home['body'], 'event-list-two'));
check('academic calendar panel is on the right', str_contains($home['body'], 'একাডেমিক ক্যালেন্ডার') && str_contains($home['body'], 'home-calendar-card'));
check('calendar is wrapped in a turbo frame', str_contains($home['body'], 'turbo-frame id="home-calendar"'));
check('public layout still loads turbo', str_contains($home['body'], 'vendor/turbo/turbo.js'));

// November calendar
$nov = req('/?calendar=2026-11');
check('november calendar page loads', $nov['status'] === 200, (string) $nov['status']);
check('november title uses bengali numerals and month', str_contains($nov['body'], 'নভেম্বর ২০২৬'));
check('november has 30 numbered day cells', str_contains($nov['body'], '১৬') && countTags($nov['body'], 'home-cal-day') >= 30);
check('november holiday is listed', str_contains($nov['body'], 'শহীদ দিবস (ভিক্টোরি ডে)'));
check('november holiday date label is bengali', str_contains($nov['body'], '১৬ নভেম্বর'));
check('weekly off days are marked', str_contains($nov['body'], 'home-cal-day is-weekly-off'));
check('the 16th is marked as a holiday', str_contains($nov['body'], 'শহীদ দিবস'));

// December calendar
$dec = req('/?calendar=2026-12');
check('december calendar page loads', $dec['status'] === 200, (string) $dec['status']);
check('december title shown', str_contains($dec['body'], 'ডিসেম্বর ২০২৬'));
check('december multi-day holiday is listed', str_contains($dec['body'], 'শীতকালীন অবকাশ'));
check('december holiday shows a date range', str_contains($dec['body'], '২৫–৩১ ডিসেম্বর'));
check('december prev month link points to november', str_contains($dec['body'], '/?calendar=2026-11') || str_contains($dec['body'], '?calendar=2026-11'));

// Invalid month falls back to the current month without crashing.
$invalid = req('/?calendar=bad');
check('invalid month still returns 200', $invalid['status'] === 200, (string) $invalid['status']);
check('invalid month still renders a calendar title', str_contains($invalid['body'], 'home-cal-nav'));

// Cleanup QA holidays so the database is not polluted across runs.
Holiday::where('description', 'QA holiday')->delete();

echo PHP_EOL."=== RESULT: $passed passed, $failed failed ===\n";
exit($failed > 0 ? 1 : 0);
