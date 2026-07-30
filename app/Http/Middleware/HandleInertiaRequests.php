<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;
use Lumina\Coupon\Providers\CouponServiceProvider;
use Lumina\Ecommerce\Providers\EcommerceServiceProvider;
use Lumina\Locations\Providers\LocationsServiceProvider;
use Lumina\Social\Providers\SocialServiceProvider;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'cms::app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'locales' => config('app.locales'),
            'enabled_plugins' => [
                'locations' => class_exists(LocationsServiceProvider::class),
                'ecommerce' => class_exists(EcommerceServiceProvider::class),
                'coupon' => class_exists(CouponServiceProvider::class),
                'social' => class_exists(SocialServiceProvider::class),
            ],
            'setting' => fn () => $request->user()
                ? [
                    'site_name' => app()->setting('system.site_name'),
                    'logo_url' => ($logo = app()->setting('system.logo')) ? Storage::disk('public')->url($logo) : null,
                    'favicon_url' => ($favicon = app()->setting('system.favicon')) ? Storage::disk('public')->url($favicon) : null,
                ]
                : null,
        ];
    }
}
