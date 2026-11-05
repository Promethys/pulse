<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordIfPresent
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, ?string $redirectToRoute = null, string|int|null $passwordTimeoutSeconds = null): Response
    {
        if ($request->user()?->has_password) {
            return app(RequirePassword::class)->handle($request, $next, $redirectToRoute, $passwordTimeoutSeconds);
        }

        return $next($request);
    }
}
