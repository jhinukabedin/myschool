<?php

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use Illuminate\Support\Facades\DB;

echo 'sessions: '.AcademicSession::count()."\n";
AcademicSession::all()->each(fn ($s) => print("  #{$s->id} {$s->name} {$s->start_date} -> {$s->end_date}\n"));
echo 'current session id: '.optional(current_session())->id."\n";
echo 'students: '.Student::count()."\n";
echo 'enrollments: '.Enrollment::count()."\n";
Enrollment::select('id', 'student_id', 'academic_session_id', 'status')->limit(8)->get()
    ->each(fn ($e) => print("  enr#{$e->id} student#{$e->student_id} session#{$e->academic_session_id} {$e->status}\n"));
echo 'fee assignments: '.StudentFeeAssignment::count()."\n";
echo 'fee categories: '.DB::table('fee_categories')->count()."\n";
