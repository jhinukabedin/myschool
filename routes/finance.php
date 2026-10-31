<?php

use App\Http\Controllers\Finance\ExpenseController;
use Illuminate\Support\Facades\Route;

Route::get('/expenses', [ExpenseController::class, 'index'])->middleware('permission:finance.view')->name('expenses.index');
Route::get('/expenses/create', [ExpenseController::class, 'create'])->middleware('permission:finance.manage')->name('expenses.create');
Route::post('/expenses', [ExpenseController::class, 'store'])->middleware('permission:finance.manage')->name('expenses.store');
Route::post('/expenses/categories', [ExpenseController::class, 'storeCategory'])->middleware('permission:finance.manage')->name('expenses.categories.store');
Route::get('/expenses/{expense}/edit', [ExpenseController::class, 'edit'])->middleware('permission:finance.manage')->name('expenses.edit');
Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->middleware('permission:finance.manage')->name('expenses.update');
Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->middleware('permission:finance.manage')->name('expenses.destroy');
