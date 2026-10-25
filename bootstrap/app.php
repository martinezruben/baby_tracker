<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureSetupState;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use App\Models\Family;
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
    ->withMiddleware(function (Middleware $middleware): void {
        // Los botones de los avisos llevan enlaces firmados y no tienen sesión ni token CSRF.
        $middleware->validateCsrfTokens(except: ['avisos/*']);
        $middleware->alias(['setup' => EnsureSetupState::class, 'admin' => EnsureAdmin::class]);
        $middleware->redirectGuestsTo(fn () => Family::query()->exists() ? route('login') : route('setup.show'));
        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
