<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Database\Connectors\NeonPostgresConnector;
use App\Models\Invitation;
use App\Policies\InvitationPolicy;
use App\Services\Payment\MidtransGateway;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, MidtransGateway::class);
        $this->app->bind('db.connector.pgsql', NeonPostgresConnector::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Invitation::class, InvitationPolicy::class);
    }
}
