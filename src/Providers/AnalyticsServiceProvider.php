<?php

namespace Epaisay\Analytics\Providers;

use Illuminate\Support\ServiceProvider;
use Epaisay\Analytics\Console\Commands\AggregateAnalytics;
use Epaisay\Analytics\Console\Commands\InstallAnalytics;
use Epaisay\Analytics\Middleware\TrackAnalytics;
use Epaisay\Analytics\Observers\AnalyticsObserver;
use Epaisay\Analytics\Models\Analytic;

class AnalyticsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge package configuration
        $this->mergeConfigFrom(
            __DIR__.'/../../config/analytics.php', 'analytics'
        );

        // Register the main service
        $this->app->singleton('analytics', function ($app) {
            return new \Epaisay\Analytics\Helpers\AnalyticsHelper;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../../config/analytics.php' => config_path('analytics.php'),
        ], 'analytics-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../../database/migrations/' => database_path('migrations'),
        ], 'analytics-migrations');

        // Publish stubs
        $this->publishes([
            __DIR__.'/../../stubs/' => base_path('stubs/analytics'),
        ], 'analytics-stubs');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                AggregateAnalytics::class,
                InstallAnalytics::class,
            ]);
        }

        // Register middleware
        $this->app['router']->aliasMiddleware('track.analytics', TrackAnalytics::class);

        // Register observer
        Analytic::observe(AnalyticsObserver::class);
    }
}