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
        // Feature tests run in console mode but still exercise HTTP middleware.
        if (! app()->runningInConsole() || app()->runningUnitTests()) {
            Cache::lock('invitation-preview-sweep', 60)->get(function (): void {
                $service = app(InvitationExpiryService::class);
                $service->expireDue();

                // Pruning is comparatively expensive; at most one check every 6 hours.
                if (Cache::add('invitation-preview-prune-check', true, now()->addHours(6))) {
                    $service->pruneExpired(30);
                }
            });
        }

        return $next($request);
    }
}
