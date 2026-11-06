<?php

/**
 * Verifies the student payment slip matches the locked design exactly and is
 * fully data-driven. Also checks the portal receipts still render.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Payment;

const BASE = 'http://newschool.test';
const COOKIE = __DIR__.'/cookie-slip.jar';

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

$payment = Payment::with('student')->whereHas('student')->latest('paid_at')->firstOrFail();
$body = req('GET', '/payments/'.$payment->id.'/receipt');

echo "\n=== Page renders ===\n";
check('receipt loads', $body['status'] === 200, 'status '.$body['status']);
check('title matches design', str_contains($body['body'], '<title>'.school_name().' - Payment Slip</title>'));

echo "\n=== Locked CSS is untouched ===\n";
$locked = [
    '@page{size:A4;margin:0}',
    '.page{width:210mm;min-height:297mm;max-height:297mm;padding:11mm 12mm 0 12mm;position:relative;overflow:hidden;display:flex;flex-direction:column}',
    ':root{--p:#5b21b6;--pd:#4c1d95;--pl:#ede9fe;--pl2:#f5f3ff;--line:#ddd6fe}',
    '.school{font-size:30pt;font-weight:700;color:var(--pd);letter-spacing:-.5px;line-height:1}',
    '.badge{display:inline-block;background:var(--p);color:#fff;font-weight:700;font-size:13pt;letter-spacing:.6px;padding:6px 14px;border-radius:7px}',
    '.sec{margin-top:12px;border:1.5px solid var(--line);border-radius:9px;overflow:hidden;break-inside:avoid}',
    '.totalrow td{background:var(--pl);font-weight:700;color:var(--pd);font-size:11pt;border-top:2px solid var(--p)}',
    '.wave{background:var(--pd);color:#fff;text-align:center;font-size:9.5pt;padding:9px 0 10px;margin:0 -12mm;border-radius:50% 50% 0 0/14px 14px 0 0}',
    'html,body{width:210mm!important;height:297mm!important;margin:0!important;padding:0!important;background:#fff!important;overflow:hidden!important;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}',
    '.sec,.cols,.bottom,.words,.status,.box,table,tr,td,th{break-inside:avoid!important;page-break-inside:avoid!important}',
];
foreach ($locked as $i => $rule) {
    check('CSS rule '.($i + 1).' unchanged', str_contains($body['body'], $rule));
}

echo "\n=== Locked structure ===\n";
foreach (['class="hdr"', 'class="crest"', 'class="titlebox"', 'class="badgebox"', 'class="contact"', 'STUDENT INFORMATION', 'FEE DETAILS', 'PAYMENT SUMMARY', 'Amount in Words', 'Received By', 'Authorised Signature', 'Jhinuk Tech', 'Developed by'] as $marker) {
    check('has '.$marker, str_contains($body['body'], $marker));
}

preg_match_all('/<i class="(?:w)?"><\/i>/', $body['body'], $qr);
check('real QR container present', str_contains($body['body'], 'id="slip-qr"'));
check('QR payload embedded', str_contains($body['body'], 'data-qr="EDUSPHERE|'.$payment->receipt_no));
check('QR library included', str_contains($body['body'], 'vendor/qrcode/qrcode.js'));
check('QR is generated client-side', str_contains($body['body'], 'createSvgTag'));
check('QR library file exists', file_exists(public_path('vendor/qrcode/qrcode.js')));
check('no decorative QR cells left', ! str_contains($body['body'], '<i class="w"></i>'));

echo "\n=== Data is dynamic ===\n";
check('receipt number', str_contains($body['body'], $payment->receipt_no), $payment->receipt_no);
check('student name', str_contains($body['body'], (string) $payment->student->full_name));
check('student ID', str_contains($body['body'], (string) $payment->student->student_code));
check('amount paid formatted', str_contains($body['body'], number_format((float) $payment->amount, 2)));
check('amount in words present', str_contains($body['body'], amount_in_words((float) $payment->amount)));
check('method label', str_contains($body['body'], $payment->method_label));
check('status pill has a value', (bool) preg_match('/<span class="pill">(PAID|PARTIAL|UNPAID|REVERSED)<\/span>/', $body['body']));
check('screen toolbar is print-hidden', str_contains($body['body'], '.screen-bar,.screen-only{display:none!important}'));

echo "\n=== Print mode ===\n";
$print = req('GET', '/payments/'.$payment->id.'/receipt?print=1');
check('print=1 loads', $print['status'] === 200, 'status '.$print['status']);
check('auto print script present', str_contains($print['body'], 'window.print()'));

echo "\n=== No legacy slip markup ===\n";
check('old receipt-sheet removed', ! str_contains($body['body'], 'receipt-sheet'));
check('old receipt-grid removed', ! str_contains($body['body'], 'receipt-grid'));

echo "\n=== RESULT: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
