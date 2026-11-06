<?php

use App\Http\Controllers\Admin\AcademicCornerController;
use App\Http\Controllers\Admin\AcademicSessionController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\PortalAccountController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SchoolClassController;
use App\Http\Controllers\Admin\SectionController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\UserAccessController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WebsiteAdminController;
use App\Http\Controllers\Admin\WebsiteContentController;
use App\Http\Controllers\Admin\WebsiteMenuController;
use Illuminate\Support\Facades\Route;

Route::get('/admin', [AdminController::class, 'index'])
    ->middleware('permission:settings.view')->name('admin.index');

Route::prefix('admin/access')->name('access.')->middleware('permission:access.manage')->group(function () {
    Route::get('/', [UserAccessController::class, 'index'])->name('index');
    Route::put('/{user}', [UserAccessController::class, 'update'])->name('update');
    Route::post('/{user}/template', [UserAccessController::class, 'template'])->name('template');
});

Route::get('/admin/subscription', [SubscriptionController::class, 'index'])
    ->middleware('permission:subscription.view')->name('subscription.index');
Route::put('/admin/subscription', [SubscriptionController::class, 'update'])
    ->middleware('permission:subscription.manage')->name('subscription.update');
Route::put('/admin/subscription/feature-access', [SubscriptionController::class, 'updateGrants'])
    ->middleware('permission:subscription.manage')->name('subscription.grants.update');

Route::prefix('sessions')->name('sessions.')->middleware('permission:academics.manage')->group(function () {
    Route::get('/', [AcademicSessionController::class, 'index'])->name('index');
    Route::post('/', [AcademicSessionController::class, 'store'])->name('store');
    Route::put('/{session}', [AcademicSessionController::class, 'update'])->name('update');
    Route::post('/{session}/activate', [AcademicSessionController::class, 'activate'])->name('activate');
    Route::delete('/{session}', [AcademicSessionController::class, 'destroy'])->name('destroy');
});

Route::prefix('classes')->name('classes.')->middleware('permission:academics.manage')->group(function () {
    Route::get('/', [SchoolClassController::class, 'index'])->name('index');
    Route::post('/', [SchoolClassController::class, 'store'])->name('store');
    Route::put('/{schoolClass}', [SchoolClassController::class, 'update'])->name('update');
    Route::delete('/{schoolClass}', [SchoolClassController::class, 'destroy'])->name('destroy');
});

Route::prefix('sections')->name('sections.')->middleware('permission:academics.manage')->group(function () {
    Route::get('/', [SectionController::class, 'index'])->name('index');
    Route::post('/', [SectionController::class, 'store'])->name('store');
    Route::put('/{section}', [SectionController::class, 'update'])->name('update');
    Route::delete('/{section}', [SectionController::class, 'destroy'])->name('destroy');
});

Route::prefix('users')->name('users.')->middleware('permission:users.manage')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::get('/create', [UserController::class, 'create'])->name('create');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
    Route::put('/{user}', [UserController::class, 'update'])->name('update');
    Route::post('/{user}/status', [UserController::class, 'toggle'])->name('toggle');
    Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
});

Route::prefix('roles')->name('roles.')->middleware('permission:users.manage')->group(function () {
    Route::get('/', [RoleController::class, 'index'])->name('index');
    Route::post('/', [RoleController::class, 'store'])->name('store');
    Route::put('/{role}/details', [RoleController::class, 'updateDetails'])->name('details.update');
    Route::delete('/{role}', [RoleController::class, 'destroy'])->name('destroy');
    Route::put('/{role}', [RoleController::class, 'update'])->name('update');
});

Route::get('/audit-log', [AuditLogController::class, 'index'])
    ->middleware('permission:audit.view')->name('audit.index');

Route::prefix('admin/portal-accounts')->name('portal-accounts.')
    ->middleware('permission:portals.manage')->group(function () {
        Route::get('/', [PortalAccountController::class, 'index'])->name('index');
        Route::post('/', [PortalAccountController::class, 'store'])->name('store');
        Route::delete('/students/{link}', [PortalAccountController::class, 'destroyStudent'])->name('students.destroy');
        Route::delete('/parents/{link}', [PortalAccountController::class, 'destroyParent'])->name('parents.destroy');
    });

Route::get('/settings', [SettingController::class, 'index'])
    ->middleware('permission:settings.view')->name('settings.index');
Route::put('/settings', [SettingController::class, 'update'])
    ->middleware('permission:settings.manage')->name('settings.update');

Route::prefix('admin/website')->name('website-admin.')->middleware('permission:website.view')->group(function () {
    Route::get('/', [WebsiteAdminController::class, 'index'])->name('index');
    Route::get('/design', [WebsiteAdminController::class, 'design'])->name('design');
    Route::put('/design', [WebsiteAdminController::class, 'updateDesign'])
        ->middleware('permission:website.manage')->name('design.update');

    // Header / Footer / Hero settings
    Route::get('/header', [WebsiteAdminController::class, 'header'])->name('header');
    Route::put('/header', [WebsiteAdminController::class, 'updateHeader'])
        ->middleware('permission:website.manage')->name('header.update');
    Route::get('/footer', [WebsiteAdminController::class, 'footer'])->name('footer');
    Route::put('/footer', [WebsiteAdminController::class, 'updateFooter'])
        ->middleware('permission:website.manage')->name('footer.update');
    Route::get('/hero', [WebsiteAdminController::class, 'hero'])->name('hero');
    Route::put('/hero', [WebsiteAdminController::class, 'updateHero'])
        ->middleware('permission:website.manage')->name('hero.update');

    // Choose which blocks are visible on the public homepage.
    Route::put('/home-sections', [WebsiteAdminController::class, 'updateHomeSections'])
        ->middleware('permission:website.manage')->name('home-sections.update');

    // Sliders
    Route::get('/sliders', [WebsiteAdminController::class, 'sliders'])->name('sliders');

    // Featured Menu
    Route::get('/featured-menu', [WebsiteAdminController::class, 'featuredMenu'])->name('featured-menu');

    // Sidebar
    Route::get('/sidebar', [WebsiteAdminController::class, 'sidebar'])->name('sidebar');

    // Public result search settings.
    Route::get('/result-search', [WebsiteAdminController::class, 'resultSearch'])->name('result-search');
    Route::put('/result-search', [WebsiteAdminController::class, 'updateResultSearch'])
        ->middleware('permission:website.manage')->name('result-search.update');

    // Create a new CMS page (reuses the existing content form).
    Route::get('/create-page', fn () => redirect()->route('website-content.create', ['type' => 'page']))
        ->middleware('permission:website.manage')
        ->name('create-page');

    // Notice management is provided by the communication module. This page
    // gives the website area a direct overview with quick create/edit links.
    Route::get('/notice', [WebsiteAdminController::class, 'notice'])->name('notice');

    // Academic Corner cards (same quick add/reorder pattern as the featured menu).
    Route::get('/academic-corner', [AcademicCornerController::class, 'index'])->name('academic-corner');
    Route::post('/academic-corner/cards', [AcademicCornerController::class, 'store'])
        ->middleware('permission:website.manage')->name('academic-corner.store');
    Route::put('/academic-corner/cards/{card}', [AcademicCornerController::class, 'update'])
        ->middleware('permission:website.manage')->name('academic-corner.update');
    Route::post('/academic-corner/cards/{card}/move', [AcademicCornerController::class, 'move'])
        ->middleware('permission:website.manage')->name('academic-corner.move');
    Route::delete('/academic-corner/cards/{card}', [AcademicCornerController::class, 'destroy'])
        ->middleware('permission:website.manage')->name('academic-corner.destroy');

    // One dedicated function per homepage / public content area. Academic
    // Corner has its own card manager above.
    foreach (array_keys(WebsiteContentController::SECTIONS) as $section) {
        if ($section === 'academic-corner') {
            continue;
        }

        Route::get('/'.$section, [WebsiteContentController::class, 'section'])
            ->defaults('section', $section)
            ->name($section);
    }
});

Route::prefix('admin/website/content')->name('website-content.')->middleware('permission:website.view')->group(function () {
    Route::get('/', [WebsiteContentController::class, 'index'])->name('index');
    Route::get('/create', [WebsiteContentController::class, 'create'])->middleware('permission:website.manage')->name('create');
    Route::post('/image', [WebsiteContentController::class, 'uploadImage'])->middleware('permission:website.manage')->name('image');
    Route::post('/', [WebsiteContentController::class, 'store'])->middleware('permission:website.manage')->name('store');
    Route::get('/{content}/edit', [WebsiteContentController::class, 'edit'])->middleware('permission:website.manage')->name('edit');
    Route::put('/{content}', [WebsiteContentController::class, 'update'])->middleware('permission:website.manage')->name('update');
    Route::delete('/{content}', [WebsiteContentController::class, 'destroy'])->middleware('permission:website.manage')->name('destroy');
    Route::post('/{content}/archive', [WebsiteContentController::class, 'archive'])->middleware('permission:website.manage')->name('archive');
    Route::post('/{content}/toggle', [WebsiteContentController::class, 'toggleStatus'])->middleware('permission:website.manage')->name('toggle');
    Route::post('/{content}/revisions/{revision}/restore', [WebsiteContentController::class, 'restoreRevision'])
        ->middleware('permission:website.publish')->name('revisions.restore');
});

Route::prefix('admin/menus')->name('website-menus.')->middleware('permission:website.menus')->group(function () {
    Route::get('/', [WebsiteMenuController::class, 'index'])->name('index');
    Route::post('/', [WebsiteMenuController::class, 'store'])->name('store');
    Route::put('/{menu}', [WebsiteMenuController::class, 'update'])->name('update');
    Route::delete('/{menu}', [WebsiteMenuController::class, 'destroy'])->name('destroy');
    Route::post('/{menu}/items', [WebsiteMenuController::class, 'storeItem'])->name('items.store');
    Route::put('/items/{item}', [WebsiteMenuController::class, 'updateItem'])->name('items.update');
    Route::delete('/items/{item}', [WebsiteMenuController::class, 'destroyItem'])->name('items.destroy');
    Route::post('/items/{item}/move', [WebsiteMenuController::class, 'moveItem'])->name('items.move');});

Route::prefix('admin/media')->name('media.')->middleware('permission:website.media')->group(function () {
    Route::get('/', [MediaController::class, 'index'])->name('index');
    Route::post('/', [MediaController::class, 'store'])->name('store');
    Route::put('/{medium}', [MediaController::class, 'update'])->name('update');
    Route::delete('/{medium}', [MediaController::class, 'destroy'])->name('destroy');
});

Route::prefix('admin/messages')->name('messages.')->middleware('permission:website.view')->group(function () {
    Route::get('/', [ContactMessageController::class, 'index'])->name('index');
    Route::get('/{message}', [ContactMessageController::class, 'show'])->name('show');
    Route::post('/{message}/status', [ContactMessageController::class, 'status'])->middleware('permission:website.manage')->name('status');
});
