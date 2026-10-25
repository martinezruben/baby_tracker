<?php

namespace App\Http\Middleware;

use App\Enums\CaretakerRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Los ajustes de la familia (cuidadores, bebés, preferencias) son solo para administradores. */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->role === CaretakerRole::Admin, 403);

        return $next($request);
    }
}
