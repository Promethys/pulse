<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboarded
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->hasVerifiedEmail()) {
            $settings = $user->digestSettings()->firstOrCreate();

            if ($settings->onboarded_at === null) {
                return to_route('onboarding.show');
            }
        }

        return $next($request);
    }
}
