<?php

namespace App\Providers;

use App\Tracking\CarrierConnectorManager;
use App\Tracking\Connectors\HapagLloydConnector;
use App\Tracking\Connectors\MscConnector;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CarrierConnectorManager::class, fn ($app) => new CarrierConnectorManager([
            $app->make(HapagLloydConnector::class),
            $app->make(MscConnector::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api-token', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });
    }
}
