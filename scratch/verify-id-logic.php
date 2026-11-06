<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Services\StudentCodeService;
use Illuminate\Support\Facades\DB;

$pass = 0;
$fail = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo sprintf("  [%s] %s%s\n", $ok ? 'OK  ' : 'FAIL', $label, $detail !== '' ? ' — '.$detail : '');
}

$svc = app(StudentCodeService::class);
$session = AcademicSession::where('name', '2026')->firstOrFail();

echo "\n=== Class code mapping (numeric_level + 1) ===\n";
foreach ([['Play Group', 0, '01'], ['Nursery', 1, '02'], ['KG', 2, '03'], ['Class 1', 3, '04'], ['Class 10', 12, '13']] as [$name, $level, $expected]) {
    $class = SchoolClass::where('name', $name)->first();
    if (! $class) {
        check("class {$name} exists", false);
        continue;
    }
    check("{$name} (level {$level}) → code {$expected}", $svc->classCode($class) === $expected, $svc->classCode($class));
}

echo "\n=== New custom class gets a dynamic code ===\n";
DB::beginTransaction();
try {
    $custom = SchoolClass::create(['name' => 'Class 11 Test', 'numeric_level' => 13]);
    check('new class level 13 → code 14', $svc->classCode($custom) === '14', $svc->classCode($custom));

    $section = Section::first();
    $student = Student::create([
        'admission_no' => 'ADM-TEST-0001',
        'first_name' => 'Testid',
        'last_name' => 'Dynamic',
        'gender' => 'male',
        'date_of_birth' => '2015-01-01',
        'admission_date' => '2026-02-01',
        'status' => 'active',
    ]);
    $enrollment = Enrollment::create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'class_id' => $custom->id,
        'section_id' => $section->id,
        'roll_no' => 1,
        'status' => 'active',
    ]);

    $code = $svc->ensure($student, $enrollment);
    check('generated ID uses YY + class code + serial', $code === '2614001', $code);

    // Promotion must not change the ID.
    $otherClass = SchoolClass::where('name', 'Class 5')->first();
    Enrollment::create([
        'student_id' => $student->id,
        'academic_session_id' => AcademicSession::where('name', '2025')->value('id'),
        'class_id' => $otherClass->id,
        'section_id' => $section->id,
        'roll_no' => 15,
        'status' => 'active',
    ]);
    $student->refresh();
    $again = $svc->ensure($student->fresh());
    check('ID unchanged after promotion / class change', $again === $code, $again);

    // Changing the class level must not alter existing IDs.
    $custom->update(['numeric_level' => 20]);
    check('ID unchanged after class level edit', $student->fresh()->student_code === $code, (string) $student->fresh()->student_code);
    check('future code reflects the new level', $svc->classCode($custom->fresh()) === '21', $svc->classCode($custom->fresh()));
} finally {
    DB::rollBack();
}

echo "\n=== Duplicate numeric level is rejected ===\n";
DB::beginTransaction();
try {
    $dupe = SchoolClass::create(['name' => 'Duplicate Level Test', 'numeric_level' => 2]); // KG already uses 2
    $threw = false;
    try {
        $svc->classCode($dupe);
    } catch (\Illuminate\Validation\ValidationException $e) {
        $threw = true;
    }
    check('duplicate level blocked before issuing an ID', $threw);
} finally {
    DB::rollBack();
}

echo "\n=== Existing data integrity ===\n";
check('no null Student IDs', Student::whereNull('student_code')->orWhere('student_code', '')->count() === 0);
check('all IDs are 7 digits', Student::whereRaw("student_code NOT REGEXP '^[0-9]{7}$'")->count() === 0);
check('no duplicate Student IDs', Student::select('student_code')->groupBy('student_code')->havingRaw('COUNT(*) > 1')->count() === 0);

echo "\n=== RESULT: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
