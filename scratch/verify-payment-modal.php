<?php

/**
 * Verifies the receipt-details modal on Payment History:
 *  - the JSON details endpoint returns the receipt, student, lines and URLs
 *  - the history page exposes a modal trigger instead of a details link
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Payment;

const BASE = 'http://newschool.test';
const COOKIE = __DIR__.'/cookie-paymodal.jar';

$pass = 0;
$fail = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo sprintf("  [%s] %s%s\n", $ok ? 'OK  ' : 'FAIL', $label, $detail !== '' ? ' — '.$detail : '');
}
function req(string $method, string $path, array $form = [], array $headers = []): array
{
    $ch = curl_init(BASE.$path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => COOKIE, CURLOPT_COOKIEFILE => COOKIE,
        CURLOPT_HTTPHEADER => array_merge(['Accept: text/html'], $headers), CURLOPT_TIMEOUT => 60,
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

echo "\n=== History page modal wiring ===\n";
$page = req('GET', '/fees/payment-history');
check('page loads', $page['status'] === 200, 'status '.$page['status']);
check('details button calls the details store', str_contains($page['body'], '$store.paymentDetails.load'));
check('modal component present', str_contains($page['body'], "'payment-details'" ) || str_contains($page['body'], 'payment-details'));
check('no direct receipt link on the row action', ! str_contains($page['body'], 'title="View receipt"'));
check('print link still present', str_contains($page['body'], 'print=1'));

echo "\n=== Details endpoint ===\n";
$json = req('GET', '/payments/'.$payment->id.'/details', [], ['Accept: application/json']);
check('details endpoint returns 200', $json['status'] === 200, 'status '.$json['status']);

$data = json_decode($json['body'], true);
check('valid JSON', is_array($data));
check('receipt number returned', ($data['receipt_no'] ?? null) === $payment->receipt_no, $data['receipt_no'] ?? 'missing');
check('student name returned', ($data['student']['name'] ?? null) === $payment->student->full_name);
check('student ID returned', (string) ($data['student']['student_code'] ?? '') === (string) $payment->student->student_code);
check('session returned', filled($data['session'] ?? null));
check('method returned', filled($data['method'] ?? null));
check('lines array returned', isset($data['lines']) && is_array($data['lines']) && count($data['lines']) > 0, (string) count($data['lines'] ?? []));
check('first line has fee + amount', isset($data['lines'][0]['fee'], $data['lines'][0]['amount']));
check('total amount matches', abs((float) ($data['amount'] ?? 0) - (float) $payment->amount) < 0.01);
check('receipt_url present', filled($data['receipt_url'] ?? null));
check('print_url includes print=1', str_contains((string) ($data['print_url'] ?? ''), 'print=1'));
check('received_by returned', filled($data['received_by'] ?? null));

echo "\n=== RESULT: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
