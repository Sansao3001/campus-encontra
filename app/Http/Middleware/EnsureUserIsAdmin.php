<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * A autorização administrativa é sempre validada no servidor a partir
     * do papel guardado na base de dados, nunca a partir do frontend.
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->isAdmin(),
            403,
            'Apenas administradores podem aceder a esta área.'
        );

        return $next($request);
    }
}
