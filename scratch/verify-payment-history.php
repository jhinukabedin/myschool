<?php

/**
 * Verifies the new Payment History page: renders, lists receipts by RCP number,
 * exposes per-receipt details + print links, and supports filters.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Payment;

const BASE = 'http://newschool.test';
const COOKIE = __DIR__.'/cookie-history.jar';

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

echo "\n=== Page renders ===\n";
$page = req('GET', '/fees/payment-history');
check('payment history loads', $page['status'] === 200, 'status '.$page['status']);
check('shows the page title', str_contains($page['body'], 'Payment History'));
check('shows Receipt no column', str_contains($page['body'], 'Receipt no'));

echo "\n=== Lists receipts by RCP number ===\n";
check('latest receipt number appears', str_contains($page['body'], $payment->receipt_no), $payment->receipt_no);
check('student name appears', str_contains($page['body'], $payment->student->full_name), $payment->student->full_name);
check('student ID appears', str_contains($page['body'], (string) $payment->student->student_code), (string) $payment->student->student_code);
check('amount rendered', str_contains($page['body'], number_format((float) $payment->amount, 2)), (string) $payment->amount);

echo "\n=== Details + print links ===\n";
check('details link present', str_contains($page['body'], '/payments/'.$payment->id.'/receipt'), '/payments/'.$payment->id.'/receipt');
check('print link present', str_contains($page['body'], 'print=1'));

$receipt = req('GET', '/payments/'.$payment->id.'/receipt');
check('receipt detail page loads', $receipt['status'] === 200, 'status '.$receipt['status']);
check('receipt shows RCP number', str_contains($receipt['body'], $payment->receipt_no));
check('receipt has a print button', str_contains($receipt['body'], 'Print receipt') || str_contains($receipt['body'], 'window.print()'));

$auto = req('GET', '/payments/'.$payment->id.'/receipt?print=1');
check('print=1 auto-print script injected', str_contains($auto['body'], 'window.print()'));

echo "\n=== Filters ===\n";
$byReceipt = req('GET', '/fees/payment-history?q='.urlencode($payment->receipt_no));
check('search by RCP returns the receipt', str_contains($byReceipt['body'], $payment->receipt_no));

$byStudent = req('GET', '/fees/payment-history?q='.urlencode((string) $payment->student->student_code));
check('search by Student ID returns receipts', str_contains($byStudent['body'], $payment->receipt_no));

$byMethod = req('GET', '/fees/payment-history?method='.$payment->payment_method);
check('filter by method loads', $byMethod['status'] === 200 && str_contains($byMethod['body'], $payment->receipt_no));

$byStatus = req('GET', '/fees/payment-history?status=completed');
check('filter by status loads', $byStatus['status'] === 200);

$noMatch = req('GET', '/fees/payment-history?q=ZZZ-NO-SUCH-RECEIPT');
check('empty state message shown', str_contains($noMatch['body'], 'No receipts match these filters'));

echo "\n=== Sidebar entry ===\n";
check('sidebar links to payment history', str_contains($page['body'], 'fees/payment-history') && str_contains($page['body'], 'Payment History'));

echo "\n=== RESULT: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
