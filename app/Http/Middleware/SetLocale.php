<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Idioma de la petición: el del cuidador, o el de la familia, o el de la app. */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $caretaker = $request->user();

        app()->setLocale(
            $caretaker?->language
                ?? $caretaker?->family?->settings?->language
                ?? config('app.locale'),
        );

        return $next($request);
    }
}
