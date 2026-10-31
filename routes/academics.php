<?php

use App\Http\Controllers\Academic\AttendanceController;
use App\Http\Controllers\Academic\AttendanceReportController;
use App\Http\Controllers\Academic\ClassResultController;
use App\Http\Controllers\Academic\ClassRoutineController;
use App\Http\Controllers\Academic\ClassSubjectController;
use App\Http\Controllers\Academic\ExamController;
use App\Http\Controllers\Academic\GradeRuleController;
use App\Http\Controllers\Academic\GradingSchemeController;
use App\Http\Controllers\Academic\MarksController;
use App\Http\Controllers\Academic\RoutineTimeSlotController;
use App\Http\Controllers\Academic\SubjectController;
use App\Http\Controllers\Academic\TeacherAssignmentController;
use App\Http\Controllers\Academic\TeacherController;
use App\Http\Controllers\Academic\TeacherScheduleController;
use Illuminate\Support\Facades\Route;

Route::get('/teacher', [TeacherController::class, 'index'])
    ->middleware('permission:dashboard.view')->name('teacher.dashboard');

Route::get('/teacher/schedule', [TeacherScheduleController::class, 'index'])
    ->middleware('permission:routines.view')->name('teacher.schedule');

Route::get('/attendance', [AttendanceController::class, 'index'])
    ->middleware('permission:attendance.view')->name('attendance.index');
Route::post('/attendance', [AttendanceController::class, 'store'])
    ->middleware('permission:attendance.manage')->name('attendance.store');
Route::get('/attendance/report', [AttendanceReportController::class, 'index'])
    ->middleware('permission:attendance.report')->name('attendance.report');
Route::get('/attendance/report/export', [AttendanceReportController::class, 'export'])
    ->middleware('permission:attendance.report')->name('attendance.report.export');

Route::prefix('exams')->name('exams.')->group(function () {
    Route::get('/', [ExamController::class, 'index'])->middleware('permission:exams.view')->name('index');
    Route::get('/create', [ExamController::class, 'create'])->middleware('permission:exams.manage')->name('create');
    Route::post('/', [ExamController::class, 'store'])->middleware('permission:exams.manage')->name('store');
    Route::get('/schedule', [ExamController::class, 'schedule'])->middleware('permission:exams.view')->name('schedule');
    Route::get('/marks-entry', [MarksController::class, 'index'])->middleware('permission:marks.enter')->name('marks-entry');
    Route::post('/marks-entry', [MarksController::class, 'store'])->middleware('permission:marks.enter')->name('marks-store');
    Route::post('/marks-entry/submit', [MarksController::class, 'submit'])->middleware('permission:marks.submit')->name('marks-submit');
    Route::post('/marks-entry/approve', [MarksController::class, 'approve'])->middleware('permission:marks.approve')->name('marks-approve');
    Route::get('/class-result', [ClassResultController::class, 'index'])->middleware('permission:results.view')->name('class-result');
    Route::get('/class-result/export', [ClassResultController::class, 'export'])->middleware('permission:results.view')->name('class-result.export');
    Route::get('/{exam}', [ExamController::class, 'show'])->middleware('permission:exams.view')->name('show');
    Route::get('/{exam}/edit', [ExamController::class, 'edit'])->middleware('permission:exams.manage')->name('edit');
    Route::put('/{exam}', [ExamController::class, 'update'])->middleware('permission:exams.manage')->name('update');
    Route::delete('/{exam}', [ExamController::class, 'destroy'])->middleware('permission:exams.manage')->name('destroy');
    Route::post('/{exam}/publish', [ExamController::class, 'publish'])->middleware('permission:exams.manage')->name('publish');
    Route::post('/{exam}/unpublish', [ExamController::class, 'unpublish'])->middleware('permission:marks.correct')->name('unpublish');
    Route::post('/{exam}/subjects', [ExamController::class, 'storeSubject'])->middleware('permission:exams.manage')->name('subjects.store');
    Route::delete('/{exam}/subjects/{examSubject}', [ExamController::class, 'destroySubject'])->middleware('permission:exams.manage')->name('subjects.destroy');
    Route::get('/{exam}/results/{student}', [ClassResultController::class, 'show'])->middleware('permission:results.view')->name('result-sheet');
});

Route::get('/subjects', [SubjectController::class, 'index'])->middleware('permission:academics.view')->name('subjects.index');
Route::get('/subjects/create', [SubjectController::class, 'create'])->middleware('permission:academics.manage')->name('subjects.create');
Route::post('/subjects', [SubjectController::class, 'store'])->middleware('permission:academics.manage')->name('subjects.store');
Route::get('/subjects/{subject}/edit', [SubjectController::class, 'edit'])->middleware('permission:academics.manage')->name('subjects.edit');
Route::put('/subjects/{subject}', [SubjectController::class, 'update'])->middleware('permission:academics.manage')->name('subjects.update');
Route::delete('/subjects/{subject}', [SubjectController::class, 'destroy'])->middleware('permission:academics.manage')->name('subjects.destroy');

Route::get('/class-subjects', [ClassSubjectController::class, 'index'])->middleware('permission:academics.view')->name('class-subjects.index');
Route::post('/class-subjects', [ClassSubjectController::class, 'store'])->middleware('permission:academics.manage')->name('class-subjects.store');
Route::delete('/class-subjects/{classSubject}', [ClassSubjectController::class, 'destroy'])->middleware('permission:academics.manage')->name('class-subjects.destroy');

Route::get('/academics/grade-rules', [GradeRuleController::class, 'index'])->middleware('permission:academics.view')->name('grade-rules.index');
Route::put('/academics/grade-rules', [GradeRuleController::class, 'update'])->middleware('permission:exams.manage')->name('grade-rules.update');

Route::prefix('academics/grading-schemes')->name('grading-schemes.')->group(function () {
    Route::get('/', [GradingSchemeController::class, 'index'])->middleware('permission:exams.view')->name('index');
    Route::post('/', [GradingSchemeController::class, 'store'])->middleware('permission:exams.manage')->name('store');
    Route::get('/{scheme}/edit', [GradingSchemeController::class, 'edit'])->middleware('permission:exams.view')->name('edit');
    Route::put('/{scheme}', [GradingSchemeController::class, 'update'])->middleware('permission:exams.manage')->name('update');
    Route::delete('/{scheme}', [GradingSchemeController::class, 'destroy'])->middleware('permission:exams.manage')->name('destroy');
});

Route::prefix('class-routines')->name('routines.')->group(function () {
    Route::get('/', [ClassRoutineController::class, 'index'])
        ->middleware('permission:routines.view')->name('index');
    Route::get('/create', [ClassRoutineController::class, 'create'])
        ->middleware('permission:routines.create')->name('create');
    Route::post('/', [ClassRoutineController::class, 'store'])
        ->middleware('permission:routines.create')->name('store');

    Route::get('/settings', [RoutineTimeSlotController::class, 'index'])
        ->middleware('permission:routines.edit')->name('settings');
    Route::put('/settings/days', [RoutineTimeSlotController::class, 'updateDays'])
        ->middleware('permission:routines.edit')->name('settings.days');
    Route::post('/time-slots', [RoutineTimeSlotController::class, 'store'])
        ->middleware('permission:routines.edit')->name('time-slots.store');
    Route::put('/time-slots/{timeSlot}', [RoutineTimeSlotController::class, 'update'])
        ->middleware('permission:routines.edit')->name('time-slots.update');
    Route::post('/time-slots/{timeSlot}/move', [RoutineTimeSlotController::class, 'move'])
        ->middleware('permission:routines.edit')->name('time-slots.move');
    Route::delete('/time-slots/{timeSlot}', [RoutineTimeSlotController::class, 'destroy'])
        ->middleware('permission:routines.edit')->name('time-slots.destroy');

    Route::get('/{routine}', [ClassRoutineController::class, 'show'])
        ->middleware('permission:routines.view')->name('show');
    Route::get('/{routine}/edit', [ClassRoutineController::class, 'edit'])
        ->middleware('permission:routines.edit')->name('edit');
    Route::put('/{routine}', [ClassRoutineController::class, 'update'])
        ->middleware('permission:routines.edit')->name('update');
    Route::delete('/{routine}', [ClassRoutineController::class, 'destroy'])
        ->middleware('permission:routines.delete')->name('destroy');
    Route::get('/{routine}/copy', [ClassRoutineController::class, 'copyForm'])
        ->middleware('permission:routines.copy')->name('copy');
    Route::post('/{routine}/copy', [ClassRoutineController::class, 'copy'])
        ->middleware('permission:routines.copy')->name('copy.store');
    Route::get('/{routine}/print', [ClassRoutineController::class, 'print'])
        ->middleware('permission:routines.print')->name('print');
    Route::get('/{routine}/pdf', [ClassRoutineController::class, 'pdf'])
        ->middleware('permission:routines.print')->name('pdf');
    Route::post('/{routine}/cells', [ClassRoutineController::class, 'saveEntry'])
        ->middleware('permission:routines.edit')->name('cells.save');
    Route::post('/{routine}/cells/clear', [ClassRoutineController::class, 'clearEntry'])
        ->middleware('permission:routines.edit')->name('cells.clear');
});

Route::prefix('teacher-assignments')->name('assignments.')->group(function () {
    Route::get('/', [TeacherAssignmentController::class, 'index'])
        ->middleware('permission:assignments.view')->name('index');
    Route::get('/create', [TeacherAssignmentController::class, 'create'])
        ->middleware('permission:assignments.manage')->name('create');
    Route::post('/', [TeacherAssignmentController::class, 'store'])
        ->middleware('permission:assignments.manage')->name('store');
    Route::get('/{assignment}/edit', [TeacherAssignmentController::class, 'edit'])
        ->middleware('permission:assignments.manage')->name('edit');
    Route::put('/{assignment}', [TeacherAssignmentController::class, 'update'])
        ->middleware('permission:assignments.manage')->name('update');
    Route::post('/{assignment}/toggle', [TeacherAssignmentController::class, 'toggle'])
        ->middleware('permission:assignments.manage')->name('toggle');
});
