<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * Impedir que uma conta suspensa continue a usar tokens já emitidos.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isSuspended()) {
            abort(403, 'A tua conta está suspensa. Contacta a administração do CampusEncontra.');
        }

        return $next($request);
    }
}
