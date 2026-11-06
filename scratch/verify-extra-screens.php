<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Payment;

const BASE = 'http://newschool.test';
const COOKIE = __DIR__.'/cookie-extra.jar';

function get(string $path): array
{
    $ch = curl_init(BASE.$path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => COOKIE, CURLOPT_COOKIEFILE => COOKIE,
        CURLOPT_HTTPHEADER => ['Accept: text/html'], CURLOPT_TIMEOUT => 60,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => (string) $body];
}
function post(string $path, array $form): array
{
    $ch = curl_init(BASE.$path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => COOKIE, CURLOPT_COOKIEFILE => COOKIE,
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($form),
        CURLOPT_HTTPHEADER => ['Accept: text/html'], CURLOPT_TIMEOUT => 60,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => (string) $body];
}
function token(string $html): ?string
{
    return preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m) ? $m[1] : null;
}

$pass = 0;
$fail = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo sprintf("  [%s] %s%s\n", $ok ? 'OK  ' : 'FAIL', $label, $detail !== '' ? ' — '.$detail : '');
}

@unlink(COOKIE);
$login = get('/login');
post('/login', ['_token' => token($login['body']), 'email' => 'admin@edusphere.test', 'password' => 'Admin@12345']);

echo "\n=== Payment receipt ===\n";
$payment = Payment::with('student')->whereHas('student', fn ($q) => $q->whereNotNull('student_code'))->first();
if ($payment) {
    $receipt = get('/payments/'.$payment->id.'/receipt');
    check('receipt loads', $receipt['status'] === 200, 'status '.$receipt['status']);
    check('receipt shows Student ID label', str_contains($receipt['body'], 'Student ID'));
    check('receipt shows the ID value', str_contains($receipt['body'], (string) $payment->student->student_code), (string) $payment->student->student_code);
} else {
    echo "  (no payments in the dataset — receipt check skipped)\n";
}

echo "\n=== Promotion page ===\n";
$promo = get('/promotion');
check('promotion page loads', $promo['status'] === 200, 'status '.$promo['status']);

echo "\n=== Quick collect ===\n";
$quick = get('/fees/quick-collect');
check('quick collect loads', $quick['status'] === 200, 'status '.$quick['status']);
check('quick collect shows Student ID', str_contains($quick['body'], 'Student ID'));

echo "\n=== Student portal/profile (admin view of portal accounts) ===\n";
$portal = get('/admin/portal-accounts');
check('portal accounts loads', $portal['status'] === 200, 'status '.$portal['status']);
check('portal accounts shows Student ID', str_contains($portal['body'], 'Student ID'));

echo "\n=== RESULT: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
