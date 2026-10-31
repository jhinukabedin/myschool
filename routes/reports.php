<?php

use App\Http\Controllers\Report\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('reports')->name('reports.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])
        ->middleware('permission:reports.view')->name('index');
    Route::get('/{report}', [ReportController::class, 'show'])
        ->middleware('permission:reports.view')->name('show');
    Route::get('/{report}/export', [ReportController::class, 'export'])
        ->middleware('permission:reports.export')->name('export');
});
