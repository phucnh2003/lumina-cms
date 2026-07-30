<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;
use Lumina\Cms\Support\AppPasswordGate;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * Same admin-session + confirmed-password check as `LogViewer::auth()`
     * (see `CmsServiceProvider::boot()`) — Horizon is reachable at its own
     * `/horizon` route, outside the `web + auth` `items` middleware group.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn () => Auth::guard('admin')->check() && AppPasswordGate::confirmed());
    }
}
