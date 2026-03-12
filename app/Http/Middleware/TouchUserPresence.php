<?php

namespace App\Http\Middleware;

use App\Services\Chat\PresenceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TouchUserPresence
{
    public function __construct(
        private readonly PresenceService $presenceService,
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            $this->presenceService->touch($request->user());
        }

        return $next($request);
    }
}
