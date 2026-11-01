<?php

namespace App\Http\Middleware;

use App\Support\Installation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * setup:required => solo si ya existe una familia (si no, manda al asistente).
 * setup:pending  => solo si todavía no existe ninguna (si ya hay, manda a iniciar sesión).
 */
class EnsureSetupState
{
    public function handle(Request $request, Closure $next, string $state): Response
    {
        $done = Installation::hasFamily();

        if ($state === 'required' && ! $done) {
            return redirect()->route('setup.show');
        }

        if ($state === 'pending' && $done) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
