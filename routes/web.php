<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/website.php';
require __DIR__.'/portals.php';

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// Language switcher (available to guests and signed-in users).
Route::get('/language/{locale}', \App\Http\Controllers\LocaleController::class)->name('language.switch');

Route::middleware(['auth', 'session.resolve', \App\Http\Middleware\EnsureModuleEnabled::class])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::get('/account/password', [PasswordController::class, 'edit'])->name('password.change');
    Route::put('/account/password', [PasswordController::class, 'update'])->name('password.update');

    require __DIR__.'/students.php';
    require __DIR__.'/fees.php';
    require __DIR__.'/academics.php';
    require __DIR__.'/finance.php';
    require __DIR__.'/staff.php';
    require __DIR__.'/communication.php';
    require __DIR__.'/reports.php';
    require __DIR__.'/admin.php';
});
