<?php

/**
 * End-to-end check of Quick Fee Collect: submit the real form and confirm a
 * payment + allocation are created, then reverse everything for cleanup.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Support\FeeWindow;
use Illuminate\Support\Facades\DB;

const BASE = 'http://newschool.test';
const COOKIE = __DIR__.'/cookie-quick.jar';

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
    $headers = curl_getinfo($ch);
    curl_close($ch);

    return ['status' => $status, 'body' => (string) $body, 'redirect' => $headers['redirect_url'] ?? null];
}
function token(string $html): ?string
{
    return preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m) ? $m[1] : null;
}

@unlink(COOKIE);
$login = req('GET', '/login');
req('POST', '/login', ['_token' => token($login['body']), 'email' => 'admin@edusphere.test', 'password' => 'Admin@12345']);

$session = current_session();

// Pick a student who actually has windowed dues.
$candidate = StudentFeeAssignment::query()
    ->where('academic_session_id', $session->id)
    ->whereIn('status', ['pending', 'partial'])
    ->where(fn ($q) => $q->whereNull('due_date')->orWhere('due_date', '<=', FeeWindow::horizonDate()))
    ->withSum(['paymentAllocations as paid_total' => fn ($q) => $q->whereHas('payment', fn ($p) => $p->where('status', 'completed'))], 'amount')
    ->orderBy('due_date')
    ->get()
    ->groupBy('student_id')
    ->map(fn ($rows) => round($rows->sum(fn ($r) => max(0, (float) $r->net_amount - (float) $r->paid_amount)), 2))
    ->filter(fn ($due) => $due >= 100)
    ->sortDesc();

$studentId = $candidate->keys()->first();
check('found a student with dues', $studentId !== null);
if (! $studentId) {
    echo "\n=== RESULT: {$pass} passed, {$fail} failed ===\n";
    exit(1);
}
$student = Student::findOrFail($studentId);
$amount = 100.00;
$expected = round((float) $candidate[$studentId], 2);

echo "\n=== Quick collect form ===\n";
$page = req('GET', '/fees/quick-collect');
check('quick page loads', $page['status'] === 200, 'status '.$page['status']);
check('form uses payment_method field', str_contains($page['body'], 'name="payment_method"'));
check('form uses reference_no field', str_contains($page['body'], 'name="reference_no"'));
check('form uses note field', str_contains($page['body'], 'name="note"'));
check('form sends paid_at', str_contains($page['body'], 'name="paid_at"'));

$before = Payment::where('student_id', $student->id)->count();

echo "\n=== Submit a quick collection ===\n";
$submit = req('POST', '/fees/quick-collect', [
    '_token' => token($page['body']),
    'student_id' => $student->id,
    'amount' => $amount,
    'payment_method' => 'cash',
    'reference_no' => 'QUICK-TEST-001',
    'note' => 'quick collect verification',
    'paid_at' => now()->format('Y-m-d\TH:i'),
]);

check('request accepted (redirect to receipt)', in_array($submit['status'], [302, 303], true), 'status '.$submit['status']);
check('redirects to a receipt, not back to the form', (bool) preg_match('#/payments/\d+/receipt#', (string) $submit['redirect']), (string) $submit['redirect']);

$payment = Payment::where('student_id', $student->id)->where('reference_no', 'QUICK-TEST-001')->first();
check('payment recorded', $payment !== null);
check('payment amount matches', $payment && abs((float) $payment->amount - $amount) < 0.01, $payment ? (string) $payment->amount : '—');
check('payment method stored', $payment && $payment->payment_method === 'cash', $payment?->payment_method);
check('allocation created', $payment && $payment->allocations()->count() > 0, $payment ? (string) $payment->allocations()->count() : '0');

if ($payment) {
    $receipt = req('GET', '/payments/'.$payment->id.'/receipt');
    check('receipt renders', $receipt['status'] === 200, 'status '.$receipt['status']);
    check('receipt shows Student ID', str_contains($receipt['body'], (string) $student->student_code), (string) $student->student_code);

    // Cleanup: remove the allocation, payment and any sequence artefact.
    DB::table('payment_allocations')->where('payment_id', $payment->id)->delete();
    DB::table('payments')->where('id', $payment->id)->delete();
    DB::table('audit_logs')->where('description', 'like', '%quick collect verification%')->delete();
    echo "  cleaned up test payment #{$payment->id}\n";
}

$after = Payment::where('student_id', $student->id)->count();
check('payment count restored after cleanup', $after === $before, $after.' vs '.$before);

echo "\n=== RESULT: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
