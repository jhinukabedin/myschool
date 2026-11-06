<?php

use App\Http\Controllers\Staff\SalaryController;
use App\Http\Controllers\Staff\StaffAttendanceController;
use App\Http\Controllers\Staff\StaffController;
use App\Http\Controllers\Staff\StaffLeaveController;
use Illuminate\Support\Facades\Route;

Route::get('/staff', [StaffController::class, 'index'])->middleware('permission:staff.view')->name('staff.index');
Route::get('/staff/create', [StaffController::class, 'create'])->middleware('permission:staff.manage')->name('staff.create');
Route::get('/staff/organisation', [StaffController::class, 'organisation'])->middleware('permission:staff.view')->name('staff.organisation');
Route::get('/staff/attendance', [StaffAttendanceController::class, 'index'])->middleware('permission:staff.view')->name('staff.attendance.index');
Route::post('/staff/attendance', [StaffAttendanceController::class, 'store'])->middleware('permission:staff.manage')->name('staff.attendance.store');
Route::get('/staff/leaves', [StaffLeaveController::class, 'index'])->middleware('permission:staff.view')->name('staff.leaves.index');
Route::post('/staff/leaves', [StaffLeaveController::class, 'store'])->middleware('permission:staff.manage')->name('staff.leaves.store');
Route::post('/staff/leaves/{leave}/approve', [StaffLeaveController::class, 'approve'])->middleware('permission:staff.manage')->name('staff.leaves.approve');
Route::post('/staff/leaves/{leave}/reject', [StaffLeaveController::class, 'reject'])->middleware('permission:staff.manage')->name('staff.leaves.reject');
Route::post('/staff/leaves/{leave}/cancel', [StaffLeaveController::class, 'cancel'])->middleware('permission:staff.manage')->name('staff.leaves.cancel');
Route::post('/staff', [StaffController::class, 'store'])->middleware('permission:staff.manage')->name('staff.store');
Route::post('/staff/departments', [StaffController::class, 'storeDepartment'])->middleware('permission:staff.manage')->name('staff.departments.store');
Route::put('/staff/departments/{department}', [StaffController::class, 'updateDepartment'])->middleware('permission:staff.manage')->name('staff.departments.update');
Route::delete('/staff/departments/{department}', [StaffController::class, 'destroyDepartment'])->middleware('permission:staff.manage')->name('staff.departments.destroy');
Route::post('/staff/designations', [StaffController::class, 'storeDesignation'])->middleware('permission:staff.manage')->name('staff.designations.store');
Route::put('/staff/designations/{designation}', [StaffController::class, 'updateDesignation'])->middleware('permission:staff.manage')->name('staff.designations.update');
Route::delete('/staff/designations/{designation}', [StaffController::class, 'destroyDesignation'])->middleware('permission:staff.manage')->name('staff.designations.destroy');
Route::get('/staff/{staff}', [StaffController::class, 'show'])->middleware('permission:staff.view')->name('staff.show');
Route::get('/staff/{staff}/edit', [StaffController::class, 'edit'])->middleware('permission:staff.manage')->name('staff.edit');
Route::put('/staff/{staff}', [StaffController::class, 'update'])->middleware('permission:staff.manage')->name('staff.update');
Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->middleware('permission:staff.manage')->name('staff.destroy');
Route::post('/staff/{staff}/restore', [StaffController::class, 'restore'])->middleware('permission:staff.manage')->withTrashed()->name('staff.restore');
Route::post('/staff/{staff}/salary-structure', [StaffController::class, 'storeSalaryStructure'])->middleware('permission:payroll.manage')->name('staff.salary-structure.store');

Route::get('/payroll', [SalaryController::class, 'index'])->middleware('permission:payroll.view')->name('payroll.index');
Route::post('/payroll/generate', [SalaryController::class, 'generate'])->middleware('permission:payroll.manage')->name('payroll.generate');
Route::post('/payroll/{payroll}/process', [SalaryController::class, 'process'])->middleware('permission:payroll.manage')->name('payroll.process');
Route::post('/payroll/{payroll}/cancel', [SalaryController::class, 'cancel'])->middleware('permission:payroll.manage')->name('payroll.cancel');
Route::post('/payroll/{payroll}/pay', [SalaryController::class, 'pay'])->middleware('permission:payroll.manage')->name('payroll.pay');
Route::post('/payroll/{payroll}/reverse', [SalaryController::class, 'reverse'])->middleware('permission:payroll.manage')->name('payroll.reverse');
Route::post('/payroll/payments/{salaryPayment}/reverse', [SalaryController::class, 'reversePayment'])->middleware('permission:payroll.manage')->name('payroll.payments.reverse');
Route::get('/payroll/{payroll}/slip', [SalaryController::class, 'slip'])->middleware('permission:payroll.view')->name('payroll.slip');
Route::get('/salary-report', [SalaryController::class, 'report'])->middleware('permission:payroll.view')->name('payroll.report');
Route::get('/salary-report/export', [SalaryController::class, 'export'])->middleware('permission:payroll.view')->name('payroll.report.export');
