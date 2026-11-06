<?php

/**
 * End-to-end check for the public result search and its Website Management page.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SchoolSetting;

const BASE = 'http://newschool.test';
const COOKIE = __DIR__.'/cookie-results.jar';

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

function req(string $method, string $path, array $form = [], bool $follow = false): array
{
    $ch = curl_init(BASE.$path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_COOKIEJAR => COOKIE,
        CURLOPT_COOKIEFILE => COOKIE,
        CURLOPT_HTTPHEADER => ['Accept: text/html'],
        CURLOPT_TIMEOUT => 60,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
    }
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => (string) $body];
}

function token(string $html): ?string
{
    return preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m) ? $m[1] : null;
}

@unlink(COOKIE);

$login = req('GET', '/login');
$res = req('POST', '/login', [
    '_token' => token($login['body']),
    'email' => 'admin@edusphere.test',
    'password' => 'Admin@12345',
]);
check('admin login redirects', $res['status'] === 302, (string) $res['status']);

$page = req('GET', '/admin/website/result-search');
$token = token($page['body']);
check('result search admin page loads', $page['status'] === 200, (string) $page['status']);
check('page title present', str_contains($page['body'], 'Result Search'));
check('published exam listed', str_contains($page['body'], 'First Term Examination 2026'));
check('toggle present', str_contains($page['body'], 'website_result_search_enabled'));

$original = [
    'website_result_search_enabled' => school_setting('website_result_search_enabled', '1'),
    'website_result_title' => school_setting('website_result_title', 'রেজাল্ট'),
    'website_result_subtitle' => school_setting('website_result_subtitle', 'শিক্ষার্থী আইডি দিয়ে ফলাফল খুঁজুন'),
    'website_result_help' => school_setting('website_result_help', 'শিক্ষার্থীর আইডি নম্বর লিখুন (যেমন: 2601001)।'),
    'website_result_empty_message' => school_setting('website_result_empty_message', 'এই মুহূর্তে কোনো রেজাল্ট পাবলিশ হয়নি'),
];

$save = function (array $overrides) use (&$token) {
    $payload = array_merge([
        '_token' => $token,
        '_method' => 'PUT',
        'website_result_title' => 'রেজাল্ট',
        'website_result_subtitle' => 'শিক্ষার্থী আইডি দিয়ে ফলাফল খুঁজুন',
        'website_result_help' => 'শিক্ষার্থীর আইডি নম্বর লিখুন (যেমন: 2601001)।',
        'website_result_empty_message' => 'এই মুহূর্তে কোনো রেজাল্ট পাবলিশ হয়নি',
        'website_result_search_enabled' => '1',
    ], $overrides);

    return req('POST', '/admin/website/result-search', $payload);
};

$res = $save(['website_result_search_enabled' => '0', 'website_result_empty_message' => 'পরীক্ষার ফলাফল শীঘ্রই প্রকাশিত হবে']);
check('disable toggle redirects', $res['status'] === 302, (string) $res['status']);

$public = req('GET', '/results');
check('public shows custom empty message', str_contains($public['body'], 'পরীক্ষার ফলাফল শীঘ্রই প্রকাশিত হবে'));
check('public hides search when disabled', ! str_contains($public['body'], 'name="student_id"'));

$res = $save(['website_result_search_enabled' => '1', 'website_result_empty_message' => 'এই মুহূর্তে কোনো রেজাল্ট পাবলিশ হয়নি']);
check('enable toggle redirects', $res['status'] === 302, (string) $res['status']);

$public = req('GET', '/results');
check('public shows search when enabled', str_contains($public['body'], 'name="student_id"'));
check('public shows published exam option', str_contains($public['body'], 'First Term Examination 2026'));

$found = req('GET', '/results?exam_id=1&student_id=2604001');
check('result lookup returns 200', $found['status'] === 200, (string) $found['status']);
check('result card rendered', str_contains($found['body'], 'id="public-result"'));
check('student name rendered', str_contains($found['body'], 'Mira Islam'));
check('subject rows rendered', str_contains($found['body'], 'Bangla') && str_contains($found['body'], 'GPA'));

$missing = req('GET', '/results?exam_id=1&student_id=0000000');
check('unknown student shows not-found', str_contains($missing['body'], 'কোনো শিক্ষার্থী পাওয়া যায়নি'));

// Restore the original settings so the test leaves no visible changes.
foreach ($original as $key => $value) {
    SchoolSetting::put($key, $value, 'website');
}

check('settings restored', school_setting('website_result_search_enabled') === $original['website_result_search_enabled']);

@unlink(COOKIE);

echo PHP_EOL."=== RESULT: $passed passed, $failed failed ===\n";
exit($failed > 0 ? 1 : 0);
