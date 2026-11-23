<?php

namespace App\Http\Middleware;

use App\Services\Invitation\InvitationExpiryService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class SweepExpiredInvitations
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Run expiry sweep after response so it does not add to user TTFB.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Feature tests run in console mode but still exercise HTTP middleware.
        if (! app()->runningInConsole() || app()->runningUnitTests()) {
            try {
                if (Cache::add('invitation-preview-sweep-throttle', true, 60)) {
                    $service = app(InvitationExpiryService::class);
                    $service->expireDue();

                    // Pruning is comparatively expensive; at most one check every 6 hours.
                    if (Cache::add('invitation-preview-prune-check', true, now()->addHours(6))) {
                        $service->pruneExpired(30);
                    }
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
