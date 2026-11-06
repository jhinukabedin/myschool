<?php

use App\Http\Controllers\Finance\ExpenseController;
use App\Http\Controllers\Finance\OtherIncomeController;
use Illuminate\Support\Facades\Route;

Route::get('/expenses', [ExpenseController::class, 'index'])->middleware('permission:finance.view')->name('expenses.index');
Route::get('/other-incomes', [OtherIncomeController::class, 'index'])->middleware('permission:finance.view')->name('other-incomes.index');
Route::get('/other-incomes/create', [OtherIncomeController::class, 'create'])->middleware('permission:finance.manage')->name('other-incomes.create');
Route::post('/other-incomes', [OtherIncomeController::class, 'store'])->middleware('permission:finance.manage')->name('other-incomes.store');
Route::get('/other-incomes/{otherIncome}/edit', [OtherIncomeController::class, 'edit'])->middleware('permission:finance.manage')->name('other-incomes.edit');
Route::put('/other-incomes/{otherIncome}', [OtherIncomeController::class, 'update'])->middleware('permission:finance.manage')->name('other-incomes.update');
Route::delete('/other-incomes/{otherIncome}', [OtherIncomeController::class, 'destroy'])->middleware('permission:finance.manage')->name('other-incomes.destroy');
Route::post('/other-incomes/categories', [OtherIncomeController::class, 'storeCategory'])->middleware('permission:finance.manage')->name('other-incomes.categories.store');
Route::get('/expenses/create', [ExpenseController::class, 'create'])->middleware('permission:finance.manage')->name('expenses.create');
Route::post('/expenses', [ExpenseController::class, 'store'])->middleware('permission:finance.manage')->name('expenses.store');
Route::post('/expenses/categories', [ExpenseController::class, 'storeCategory'])->middleware('permission:finance.manage')->name('expenses.categories.store');
Route::get('/expenses/{expense}/edit', [ExpenseController::class, 'edit'])->middleware('permission:finance.manage')->name('expenses.edit');
Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->middleware('permission:finance.manage')->name('expenses.update');
Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->middleware('permission:finance.manage')->name('expenses.destroy');
