<?php

use App\Http\Controllers\Auth\PortalAuthController;
use App\Http\Controllers\Portal\ParentPortalController;
use App\Http\Controllers\Portal\StudentPortalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Frontend portals
|--------------------------------------------------------------------------
| Portal accounts use the same users table and Laravel session as staff, but
| each area is protected by an explicit role boundary. Record-level ownership
| checks live in PortalService and the portal controllers.
*/

Route::get('/student/login', [PortalAuthController::class, 'showStudent'])->name('student.login');
Route::post('/student/login', [PortalAuthController::class, 'storeStudent'])
    ->middleware('throttle:login')->name('student.login.store');

Route::get('/parent/login', [PortalAuthController::class, 'showParent'])->name('parent.login');
Route::post('/parent/login', [PortalAuthController::class, 'storeParent'])
    ->middleware('throttle:login')->name('parent.login.store');

Route::get('/teacher/login', [PortalAuthController::class, 'showTeacher'])->name('teacher.login');
Route::post('/teacher/login', [PortalAuthController::class, 'storeTeacher'])
    ->middleware('throttle:login')->name('teacher.login.store');

Route::middleware(['auth', 'session.resolve', 'portal:student'])
    ->prefix('student')->name('student.')->group(function () {
        Route::get('/dashboard', [StudentPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/profile', [StudentPortalController::class, 'profile'])->name('profile');
        Route::get('/results', [StudentPortalController::class, 'results'])->name('results');
        Route::get('/results/{exam}', [StudentPortalController::class, 'resultSheet'])->name('results.show');
        Route::get('/fees', [StudentPortalController::class, 'fees'])->name('fees');
        Route::get('/attendance', [StudentPortalController::class, 'attendance'])->name('attendance');
        Route::get('/routine', [StudentPortalController::class, 'routine'])->name('routine');
        Route::get('/assignments', [StudentPortalController::class, 'assignments'])->name('assignments');
        Route::get('/study-materials', [StudentPortalController::class, 'materials'])->name('materials');
        Route::get('/notices', [StudentPortalController::class, 'notices'])->name('notices');
        Route::get('/notices/{notice}', [StudentPortalController::class, 'notice'])->name('notices.show');
        Route::get('/calendar', [StudentPortalController::class, 'calendar'])->name('calendar');
        Route::get('/downloads', [StudentPortalController::class, 'downloads'])->name('downloads');
        Route::get('/payments/{payment}/receipt', [StudentPortalController::class, 'receipt'])
            ->name('payments.receipt');
    });

Route::middleware(['auth', 'session.resolve', 'portal:parent'])
    ->prefix('parent')->name('parent.')->group(function () {
        Route::get('/dashboard', [ParentPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/profile', [ParentPortalController::class, 'profile'])->name('profile');
        Route::get('/children/{student}/results', [ParentPortalController::class, 'results'])
            ->name('results.index');
        Route::get('/children/{student}/results/{exam}', [ParentPortalController::class, 'resultSheet'])
            ->name('results.show');
        Route::get('/children/{student}/fees', [ParentPortalController::class, 'fees'])
            ->name('fees.index');
        Route::get('/children/{student}/attendance', [ParentPortalController::class, 'attendance'])
            ->name('attendance.index');
        Route::get('/children/{student}/payments/{payment}/receipt', [ParentPortalController::class, 'receipt'])
            ->name('payments.receipt');
    });
