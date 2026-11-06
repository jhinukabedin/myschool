<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Services\FeeAssignmentService;
use Illuminate\Support\Facades\DB;

$pass = 0;
$fail = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo sprintf("  [%s] %s%s\n", $ok ? 'OK  ' : 'FAIL', $label, $detail !== '' ? ' — '.$detail : '');
}

$session = AcademicSession::where('name', '2026')->firstOrFail();
$class = SchoolClass::where('name', 'Class 2')->firstOrFail();
$section = $class->sections()->firstOrFail();
$category = FeeCategory::query()->where('type', 'monthly')->firstOrFail();

DB::beginTransaction();
try {
    $structure = FeeStructure::create([
        'academic_session_id' => $session->id,
        'class_id' => $class->id,
        'name' => 'Window test structure',
        'effective_from' => $session->start_date->toDateString(),
        'effective_to' => $session->end_date->toDateString(),
        'status' => 'active',
    ]);

    $structure->items()->create([
        'fee_category_id' => $category->id,
        'amount' => 1000,
        'discount_amount' => 0,
        'frequency' => 'monthly',
        'due_day' => 10,
        'is_optional' => false,
    ]);

    $student = Student::create([
        'admission_no' => 'ADM-WIN-0001',
        'first_name' => 'WindowTest',
        'last_name' => 'Student',
        'gender' => 'male',
        'date_of_birth' => '2015-01-01',
        'admission_date' => '2026-09-15',
        'status' => 'active',
    ]);

    Enrollment::create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'class_id' => $class->id,
        'section_id' => $section->id,
        'roll_no' => 99,
        'status' => 'active',
    ]);

    app(FeeAssignmentService::class)->assignStructure($student, $structure->load('items'), $session);

    $rows = StudentFeeAssignment::where('student_id', $student->id)
        ->where('fee_structure_id', $structure->id)
        ->orderBy('period')
        ->pluck('period')
        ->all();

    check('monthly rows start at the admission month', ($rows[0] ?? null) === '2026-09', implode(',', $rows));
    check('no rows before admission', collect($rows)->every(fn ($p) => $p >= '2026-09'), implode(',', $rows));
    check('rows run to the session end', in_array('2026-12', $rows, true), implode(',', $rows));
    check('4 months generated (Sep–Dec)', count($rows) === 4, (string) count($rows));
} finally {
    DB::rollBack();
}

echo "\n=== RESULT: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
