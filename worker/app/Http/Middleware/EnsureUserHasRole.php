<?php

namespace App\Http\Middleware;

use App\Enums\RoleKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $roleKey = RoleKey::tryFrom(mb_strtoupper($role));

        abort_if(
            $roleKey === null || ! $request->user()?->hasRole($roleKey),
            Response::HTTP_FORBIDDEN,
        );

        return $next($request);
    }
}
