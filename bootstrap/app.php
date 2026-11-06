<?php

use App\Http\Middleware\EnsurePortalRole;
use App\Http\Middleware\EnsureStaffPortalUser;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\ResolveAcademicSession;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // The date separator may intentionally contain leading/trailing spaces.
        $middleware->trimStrings(except: ['date_separator']);

        $middleware->alias([
            'permission' => EnsureUserHasPermission::class,
            'session.resolve' => ResolveAcademicSession::class,
            'portal' => EnsurePortalRole::class,
            'staff.portal' => EnsureStaffPortalUser::class,
        ]);

        // Resolve Bangla/English before the route renders any view.
        $middleware->web(append: [
            SetLocale::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => match (true) {
            $request->is('student', 'student/*') => route('student.login'),
            $request->is('parent', 'parent/*') => route('parent.login'),
            default => route('login'),
        });
        $middleware->redirectUsersTo('/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
