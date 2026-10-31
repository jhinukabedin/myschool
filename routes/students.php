<?php

use App\Http\Controllers\Student\AdmissionController;
use App\Http\Controllers\Student\EnquiryController;
use App\Http\Controllers\Student\PromotionController;
use App\Http\Controllers\Student\SessionReportController;
use App\Http\Controllers\Student\StudentController;
use Illuminate\Support\Facades\Route;

Route::prefix('admission-enquiries')->name('enquiries.')->group(function () {
    Route::get('/', [EnquiryController::class, 'index'])->middleware('permission:enquiries.view')->name('index');
    Route::get('/create', [EnquiryController::class, 'create'])->middleware('permission:enquiries.manage')->name('create');
    Route::post('/', [EnquiryController::class, 'store'])->middleware('permission:enquiries.manage')->name('store');
    Route::get('/{enquiry}/edit', [EnquiryController::class, 'edit'])->middleware('permission:enquiries.manage')->name('edit');
    Route::put('/{enquiry}', [EnquiryController::class, 'update'])->middleware('permission:enquiries.manage')->name('update');
    Route::post('/{enquiry}/follow-up', [EnquiryController::class, 'followUp'])->middleware('permission:enquiries.manage')->name('follow-up');
});

Route::get('/admissions/create', [AdmissionController::class, 'create'])
    ->middleware('permission:students.create')->name('admissions.create');
Route::post('/admissions', [AdmissionController::class, 'store'])
    ->middleware('permission:students.create')->name('admissions.store');

Route::get('/students/archive', [StudentController::class, 'archive'])
    ->middleware('permission:students.view')->name('students.archive');
Route::get('/students', [StudentController::class, 'index'])
    ->middleware('permission:students.view')->name('students.index');
Route::get('/students/{student}', [StudentController::class, 'show'])
    ->middleware('permission:students.view')->name('students.show');
Route::get('/students/{student}/edit', [StudentController::class, 'edit'])
    ->middleware('permission:students.update')->name('students.edit');
Route::put('/students/{student}', [StudentController::class, 'update'])
    ->middleware('permission:students.update')->name('students.update');
Route::patch('/students/{student}/status', [StudentController::class, 'updateStatus'])
    ->middleware('permission:students.archive')->name('students.status');

Route::get('/promotion', [PromotionController::class, 'index'])
    ->middleware('permission:students.promote')->name('promotion.index');
Route::post('/promotion', [PromotionController::class, 'store'])
    ->middleware('permission:students.promote')->name('promotion.store');

Route::get('/session-report', [SessionReportController::class, 'index'])
    ->middleware('permission:reports.view')->name('session-report.index');
Route::get('/session-report/export', [SessionReportController::class, 'export'])
    ->middleware('permission:reports.export')->name('session-report.export');
