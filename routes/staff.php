<?php

use App\Http\Controllers\Staff\SalaryController;
use App\Http\Controllers\Staff\StaffController;
use Illuminate\Support\Facades\Route;

Route::get('/staff', [StaffController::class, 'index'])->middleware('permission:staff.view')->name('staff.index');
Route::get('/staff/create', [StaffController::class, 'create'])->middleware('permission:staff.manage')->name('staff.create');
Route::post('/staff', [StaffController::class, 'store'])->middleware('permission:staff.manage')->name('staff.store');
Route::post('/staff/departments', [StaffController::class, 'storeDepartment'])->middleware('permission:staff.manage')->name('staff.departments.store');
Route::post('/staff/designations', [StaffController::class, 'storeDesignation'])->middleware('permission:staff.manage')->name('staff.designations.store');
Route::get('/staff/{staff}', [StaffController::class, 'show'])->middleware('permission:staff.view')->name('staff.show');
Route::get('/staff/{staff}/edit', [StaffController::class, 'edit'])->middleware('permission:staff.manage')->name('staff.edit');
Route::put('/staff/{staff}', [StaffController::class, 'update'])->middleware('permission:staff.manage')->name('staff.update');
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
