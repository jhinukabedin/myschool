<?php

use App\Http\Controllers\Fee\FeeCollectionController;
use App\Http\Controllers\Fee\FeeReminderController;
use App\Http\Controllers\Fee\FeeStructureController;
use App\Http\Controllers\Fee\PaymentController;
use App\Http\Controllers\Fee\QuickFeeCollectController;
use App\Http\Controllers\Fee\ScholarshipController;
use Illuminate\Support\Facades\Route;

Route::prefix('fees')->name('fees.')->group(function () {
    Route::get('structures', [FeeStructureController::class, 'index'])->middleware('permission:fees.view')->name('structures.index');
    Route::get('structures/create', [FeeStructureController::class, 'create'])->middleware('permission:fees.manage')->name('structures.create');
    Route::post('structures', [FeeStructureController::class, 'store'])->middleware('permission:fees.manage')->name('structures.store');
    Route::get('structures/{feeStructure}/edit', [FeeStructureController::class, 'edit'])->middleware('permission:fees.manage')->name('structures.edit');
    Route::put('structures/{feeStructure}', [FeeStructureController::class, 'update'])->middleware('permission:fees.manage')->name('structures.update');
    Route::post('structures/{feeStructure}/assign', [FeeStructureController::class, 'assign'])->middleware('permission:fees.manage')->name('structures.assign');

    Route::get('collection', [FeeCollectionController::class, 'index'])->middleware('permission:fees.collect|fees.view')->name('collection.index');
    Route::get('collection/{student}', [FeeCollectionController::class, 'show'])->middleware('permission:fees.collect|fees.view')->name('collection.show');
    Route::post('collection/{student}', [FeeCollectionController::class, 'store'])->middleware('permission:fees.collect')->name('collection.store');
    Route::post('assignments/{assignment}/waiver', [FeeCollectionController::class, 'waiver'])->middleware('permission:fees.waive')->name('assignments.waiver');
    Route::post('assignments/{assignment}/scholarship', [FeeCollectionController::class, 'scholarship'])->middleware('permission:fees.waive')->name('assignments.scholarship');
    Route::post('students/{student}/assign', [FeeCollectionController::class, 'assignStructure'])->middleware('permission:fees.manage')->name('assign');

    // Scholarships — full management module (Fees & Payments → Scholarships).
    // NOTE: a lightweight per-row quick action still lives on the collection page
    // (assignments.scholarship / assignments.waiver); this group manages
    // student-level scholarships with approval workflow and invoice adjustments.
    Route::get('scholarships/students', [ScholarshipController::class, 'students'])->middleware('permission:scholarships.view|scholarships.create')->name('scholarships.students');
    Route::post('scholarships/preview', [ScholarshipController::class, 'preview'])->middleware('permission:scholarships.view|scholarships.create')->name('scholarships.preview');
    Route::get('scholarships', [ScholarshipController::class, 'index'])->middleware('permission:scholarships.view|fees.waive')->name('scholarships.index');
    Route::get('scholarships/create', [ScholarshipController::class, 'create'])->middleware('permission:scholarships.create|fees.waive')->name('scholarships.create');
    Route::post('scholarships', [ScholarshipController::class, 'store'])->middleware('permission:scholarships.create|fees.waive')->name('scholarships.store');
    Route::get('scholarships/{scholarship}', [ScholarshipController::class, 'show'])->middleware('permission:scholarships.view|fees.waive')->name('scholarships.show');
    Route::put('scholarships/{scholarship}', [ScholarshipController::class, 'update'])->middleware('permission:scholarships.update|fees.waive')->name('scholarships.update');
    Route::delete('scholarships/{scholarship}', [ScholarshipController::class, 'destroy'])->middleware('permission:scholarships.delete|fees.waive')->name('scholarships.destroy');
    Route::post('scholarships/{scholarship}/approve', [ScholarshipController::class, 'approve'])->middleware('permission:scholarships.approve|fees.waive')->name('scholarships.approve');
    Route::post('scholarships/{scholarship}/reject', [ScholarshipController::class, 'reject'])->middleware('permission:scholarships.approve|fees.waive')->name('scholarships.reject');
    Route::post('scholarships/{scholarship}/suspend', [ScholarshipController::class, 'suspend'])->middleware('permission:scholarships.suspend|fees.waive')->name('scholarships.suspend');
    Route::post('scholarships/{scholarship}/reactivate', [ScholarshipController::class, 'reactivate'])->middleware('permission:scholarships.suspend|fees.waive')->name('scholarships.reactivate');
    Route::post('scholarships/{scholarship}/renew', [ScholarshipController::class, 'renew'])->middleware('permission:scholarships.create|fees.waive')->name('scholarships.renew');
    Route::post('scholarships/{scholarship}/apply-to-invoices', [ScholarshipController::class, 'applyToInvoices'])->middleware('permission:scholarships.apply_to_invoices|fees.waive')->name('scholarships.apply-to-invoices');
    Route::get('scholarships/{scholarship}/history', [ScholarshipController::class, 'history'])->middleware('permission:scholarships.view|fees.waive')->name('scholarships.history');

    Route::get('quick-collect', [QuickFeeCollectController::class, 'index'])->middleware('permission:fees.collect')->name('quick.index');
    Route::post('quick-collect', [QuickFeeCollectController::class, 'store'])->middleware('permission:fees.collect')->name('quick.store');

    Route::get('reminders', [FeeReminderController::class, 'index'])->middleware('permission:fees.view')->name('reminders.index');
    Route::post('reminders/settings', [FeeReminderController::class, 'saveSettings'])->middleware('permission:settings.manage')->name('reminders.settings');
    Route::post('reminders/{student}', [FeeReminderController::class, 'send'])->middleware('permission:communication.whatsapp')->name('reminders.send');

    Route::get('deletion-log', [PaymentController::class, 'reversals'])->middleware('permission:fees.view')->name('deletion-log');
    Route::get('payment-history', [PaymentController::class, 'history'])->middleware('permission:fees.collect|fees.view')->name('payment-history');
});

Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])
    ->middleware('permission:fees.collect|fees.view')->name('payments.receipt');
Route::get('/payments/{payment}/details', [PaymentController::class, 'details'])
    ->middleware('permission:fees.collect|fees.view')->name('payments.details');
Route::post('/payments/{payment}/reverse', [PaymentController::class, 'reverse'])
    ->middleware('permission:fees.reverse')->name('payments.reverse');
