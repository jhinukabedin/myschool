<?php

use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\Academic\ClassRoutineController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public website
|--------------------------------------------------------------------------
| These routes are deliberately outside the authenticated admin group. They
| use paths that do not collide with dashboard, student, staff, fee or academic
| administration routes. The original static site is still available intact
| under /website for reference and fallback.
*/
Route::get('/', [WebsiteController::class, 'home'])->name('website.home');

Route::get('/about', [WebsiteController::class, 'about'])->name('website.about');
Route::get('/principal-message', [WebsiteController::class, 'principalMessage'])->name('website.principal-message');
Route::get('/administration-message', [WebsiteController::class, 'administrationMessage'])->name('website.administration-message');
Route::get('/administration-message/{slug}', [WebsiteController::class, 'administrationMessageItem'])->name('website.administration-message.show');
Route::get('/president-message', [WebsiteController::class, 'presidentMessage'])->name('website.president-message');
Route::get('/academics', [WebsiteController::class, 'academics'])->name('website.academics');
Route::get('/faculty', [WebsiteController::class, 'faculty'])->name('website.faculty');

Route::get('/admission', [WebsiteController::class, 'admission'])->name('website.admission');
Route::get('/admission/apply', [WebsiteController::class, 'admissionApply'])->name('website.admission.apply');
Route::post('/admission/apply', [WebsiteController::class, 'admissionStore'])
    ->middleware('throttle:10,1')->name('website.admission.store');

Route::get('/notice-board', [WebsiteController::class, 'notices'])->name('website.notices');
Route::get('/notice-board/{notice}', [WebsiteController::class, 'notice'])->name('website.notice');

Route::get('/news', [WebsiteController::class, 'news'])->name('website.news');
Route::get('/news/{slug}', [WebsiteController::class, 'newsItem'])->name('website.news.item');

Route::get('/pages/{slug}', [WebsiteController::class, 'page'])->name('website.page');
Route::get('/events', [WebsiteController::class, 'events'])->name('website.events');
Route::get('/calendar', [WebsiteController::class, 'calendar'])->name('website.calendar');

Route::get('/gallery', [WebsiteController::class, 'gallery'])->name('website.gallery');
Route::get('/downloads', [WebsiteController::class, 'downloads'])->name('website.downloads');
Route::get('/class-routine/{routine}', [ClassRoutineController::class, 'publicShow'])->name('website.class-routine.show');
Route::get('/class-routine/{routine}/pdf', [ClassRoutineController::class, 'publicPdf'])->name('website.class-routine.pdf');
Route::get('/class-routine', [WebsiteController::class, 'classRoutine'])->name('website.class-routine');
Route::get('/results', [WebsiteController::class, 'results'])->name('website.results');
Route::get('/video-gallery', [WebsiteController::class, 'videoGallery'])->name('website.video-gallery');

Route::get('/contact', [WebsiteController::class, 'contact'])->name('website.contact');
Route::post('/contact', [WebsiteController::class, 'contactStore'])
    ->middleware('throttle:10,1')->name('website.contact.store');

Route::get('/privacy', [WebsiteController::class, 'privacy'])->name('website.privacy');

Route::get('/sitemap.xml', [WebsiteController::class, 'sitemap'])->name('website.sitemap');
Route::get('/robots.txt', [WebsiteController::class, 'robots'])->name('website.robots');
