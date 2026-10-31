<?php

use App\Http\Controllers\Fee\FeeCollectionController;
use App\Http\Controllers\Fee\FeeReminderController;
use App\Http\Controllers\Fee\FeeStructureController;
use App\Http\Controllers\Fee\PaymentController;
use App\Http\Controllers\Fee\QuickFeeCollectController;
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
    Route::post('students/{student}/assign', [FeeCollectionController::class, 'assignStructure'])->middleware('permission:fees.manage')->name('assign');

    Route::get('quick-collect', [QuickFeeCollectController::class, 'index'])->middleware('permission:fees.collect')->name('quick.index');
    Route::post('quick-collect', [QuickFeeCollectController::class, 'store'])->middleware('permission:fees.collect')->name('quick.store');

    Route::get('reminders', [FeeReminderController::class, 'index'])->middleware('permission:fees.view')->name('reminders.index');
    Route::post('reminders/settings', [FeeReminderController::class, 'saveSettings'])->middleware('permission:settings.manage')->name('reminders.settings');
    Route::post('reminders/{student}', [FeeReminderController::class, 'send'])->middleware('permission:communication.whatsapp')->name('reminders.send');

    Route::get('deletion-log', [PaymentController::class, 'reversals'])->middleware('permission:fees.view')->name('deletion-log');
});

Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])
    ->middleware('permission:fees.collect|fees.view')->name('payments.receipt');
Route::post('/payments/{payment}/reverse', [PaymentController::class, 'reverse'])
    ->middleware('permission:fees.reverse')->name('payments.reverse');
