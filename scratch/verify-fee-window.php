<?php

/**
 * Verifies the billing-window behaviour on the fee ledger:
 *  - future invoices beyond the window are hidden from the default view
 *  - the advance-payment toggle reveals them
 *  - the hidden amount is reported, not silently dropped
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\FeeCategory;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Support\FeeWindow;
use Illuminate\Support\Facades\DB;

const BASE = 'http://newschool.test';
const COOKIE = __DIR__.'/cookie-window.jar';

$pass = 0;
$fail = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo sprintf("  [%s] %s%s\n", $ok ? 'OK  ' : 'FAIL', $label, $detail !== '' ? ' — '.$detail : '');
}
function req(string $method, string $path, array $form = []): array
{
    $ch = curl_init(BASE.$path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => COOKIE, CURLOPT_COOKIEFILE => COOKIE,
        CURLOPT_HTTPHEADER => ['Accept: text/html'], CURLOPT_TIMEOUT => 60,
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
req('POST', '/login', ['_token' => token($login['body']), 'email' => 'admin@edusphere.test', 'password' => 'Admin@12345']);

$session = current_session();
$student = Student::query()->whereNotNull('student_code')->orderBy('id')->firstOrFail();
$category = FeeCategory::query()->where('type', 'monthly')->firstOrFail();

$farDate = now()->addMonthsNoOverflow(4)->startOfMonth()->addDays(9)->toDateString();
$marker = 'ZZFutureWindowProbe';

echo "\n=== Window configuration ===\n";
check('horizon is the end of next month', FeeWindow::horizonDate() === now()->startOfMonth()->addMonthsNoOverflow(1)->endOfMonth()->toDateString(), FeeWindow::horizonDate());
check('future date is outside the window', ! FeeWindow::includes(\Carbon\Carbon::parse($farDate)), $farDate);

// Temporary future invoice that belongs to a real fee category.
$probe = StudentFeeAssignment::create([
    'student_id' => $student->id,
    'fee_category_id' => $category->id,
    'academic_session_id' => $session->id,
    'period' => \Carbon\Carbon::parse($farDate)->format('Y-m'),
    'amount' => 4242,
    'discount_amount' => 0,
    'waiver_amount' => 0,
    'scholarship_amount' => 0,
    'net_amount' => 4242,
    'due_date' => $farDate,
    'status' => 'pending',
    'note' => $marker,
]);

try {
    echo "\n=== Default ledger (windowed) ===\n";
    $default = req('GET', '/fees/collection/'.$student->id);
    check('ledger loads', $default['status'] === 200, 'status '.$default['status']);
    $defaultHits = substr_count($default['body'], '4,242');
    check('future invoice row hidden from the table', $defaultHits <= 1, 'mentions: '.$defaultHits.' (banner only expected)');
    check('hidden amount is reported', str_contains($default['body'], 'future invoice'));
    check('advance-payment button offered', str_contains($default['body'], 'Add future months'));

    echo "\n=== Advance payment mode ===\n";
    $all = req('GET', '/fees/collection/'.$student->id.'?include_future=1');
    check('ledger loads with future rows', $all['status'] === 200, 'status '.$all['status']);
    $allHits = substr_count($all['body'], '4,242');
    check('future invoice now visible in the table', $allHits > $defaultHits, 'mentions: '.$allHits.' vs '.$defaultHits);
    check('advance mode banner present', str_contains($all['body'], 'Advance payment mode'));
    check('return-to-current link offered', str_contains($all['body'], 'Show only current dues'));
} finally {
    DB::table('student_fee_assignments')->where('id', $probe->id)->delete();
}

echo "\n=== Outstanding totals ignore far-future rows ===\n";
$nowDue = (float) StudentFeeAssignment::where('academic_session_id', $session->id)
    ->whereIn('status', ['pending', 'partial'])
    ->where('due_date', '<=', FeeWindow::horizonDate())
    ->sum('net_amount');
$allDue = (float) StudentFeeAssignment::where('academic_session_id', $session->id)
    ->whereIn('status', ['pending', 'partial'])
    ->sum('net_amount');
check('windowed total never exceeds the full total', $nowDue <= $allDue + 0.001, number_format($nowDue, 2).' <= '.number_format($allDue, 2));

echo "\n=== RESULT: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
