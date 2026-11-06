<?php

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AcademicSession;
use App\Models\FeeStructure;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Models\User;
use App\Services\FeeAssignmentService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;

$actor = User::where('email', 'admin@edusphere.test')->firstOrFail();
$session = AcademicSession::first();
$fees = app(FeeAssignmentService::class);
$payments = app(PaymentService::class);

$student = Student::with('enrollments')->get()->first(fn ($s) => $s->enrollments->contains('academic_session_id', $session->id));
if (! $student) {
    fwrite(STDERR, "No enrolled student found.\n");
    exit(1);
}

echo 'Student: '.$student->full_name." #{$student->id}\n";

// Fresh test fee row so existing money is untouched.
$row = StudentFeeAssignment::create([
    'student_id' => $student->id,
    'fee_category_id' => DB::table('fee_categories')->value('id'),
    'academic_session_id' => $session->id,
    'period' => null,
    'amount' => 1000,
    'discount_amount' => 0,
    'waiver_amount' => 0,
    'net_amount' => 1000,
    'due_date' => now()->toDateString(),
    'status' => 'pending',
    'note' => 'scholarship-check',
]);

echo 'Gross net: '.money($row->net_amount)." | scholarship: {$row->scholarship_label}\n";

$fees->applyScholarship($row->fresh(), 'percent', 20, $actor);
$row = $row->fresh();
echo 'After 20% scholarship → amount '.$row->scholarship_amount.' net '.$row->net_amount.' balance '.$row->balance." status {$row->status}\n";

$fees->applyScholarship($row, 'fixed', 150, $actor);
$row = $row->fresh();
echo 'After fixed 150 → amount '.$row->scholarship_amount.' net '.$row->net_amount." balance {$row->balance}\n";

$fees->applyScholarship($row, 'fixed', 5000, $actor);
$row = $row->fresh();
echo 'Capped test (5000 fixed) → scholarship '.$row->scholarship_amount.' net '.$row->net_amount." (must not go negative)\n";

$payment = $payments->collect($student, $session, [$row->id => (float) $row->net_amount], [
    'payment_method' => 'cash',
    'paid_at' => now(),
], $actor);
$payment->refresh();
echo 'Receipt '.$payment->receipt_no.' paid '.$payment->amount.' discount(scholarship) '.$payment->discount_amount.' → row status '.$row->fresh()->status."\n";

// Waiver interaction after scholarship.
$row2 = StudentFeeAssignment::create([
    'student_id' => $student->id,
    'fee_category_id' => DB::table('fee_categories')->value('id'),
    'academic_session_id' => $session->id,
    'period' => null,
    'amount' => 800,
    'discount_amount' => 100,
    'waiver_amount' => 0,
    'net_amount' => 700,
    'due_date' => now()->toDateString(),
    'status' => 'pending',
    'note' => 'scholarship-check-2',
]);
$fees->applyScholarship($row2, 'percent', 50, $actor);
$row2 = $row2->fresh();
echo 'Row2 gross 800 discount 100 + 50% scholarship → net '.$row2->net_amount." balance {$row2->balance}\n";
$fees->applyWaiver($row2, 100, 'test waiver', $actor);
echo "Row2 after 100 waiver → net {$row2->fresh()->net_amount} scholarship {$row2->fresh()->scholarship_amount}\n";

// Cleanup everything this script created.
DB::table('payment_allocations')->where('payment_id', $payment->id)->delete();
DB::table('payments')->where('id', $payment->id)->delete();
DB::table('student_fee_assignments')->whereIn('id', [$row->id, $row2->id])->delete();
DB::table('audit_logs')->where('description', 'like', '%scholarship-check%')->delete();
echo "Cleaned up test rows.\n";
