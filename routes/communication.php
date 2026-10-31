<?php

use App\Http\Controllers\Communication\NoticeController;
use Illuminate\Support\Facades\Route;

Route::prefix('notices')->name('notices.')->group(function () {
    Route::get('/', [NoticeController::class, 'index'])
        ->middleware('permission:notices.view')->name('index');
    Route::get('/create', [NoticeController::class, 'create'])
        ->middleware('permission:notices.manage')->name('create');
    Route::post('/', [NoticeController::class, 'store'])
        ->middleware('permission:notices.manage')->name('store');
    Route::post('/image', [NoticeController::class, 'uploadImage'])
        ->middleware('permission:notices.manage')->name('image');
    Route::get('/{notice}/edit', [NoticeController::class, 'edit'])
        ->middleware('permission:notices.manage')->name('edit');
    Route::put('/{notice}', [NoticeController::class, 'update'])
        ->middleware('permission:notices.manage')->name('update');
    Route::post('/{notice}/publish', [NoticeController::class, 'publish'])
        ->middleware('permission:notices.manage')->name('publish');
    Route::delete('/{notice}', [NoticeController::class, 'destroy'])
        ->middleware('permission:notices.manage')->name('destroy');
});
