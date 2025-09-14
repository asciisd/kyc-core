<?php

namespace Asciisd\KycCore\Providers;

use Asciisd\KycCore\Models\Kyc;
use Asciisd\KycCore\Services\KycManager;
use Asciisd\KycCore\Services\StatusService;
use Asciisd\KycCore\Services\ValidationService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class KycServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('kyc', function (Application $app) {
            return new KycManager(
                $app->make(StatusService::class),
                $app->make(ValidationService::class)
            );
        });

        $this->app->alias('kyc', KycManager::class);

        // Register services
        $this->app->singleton(StatusService::class);
        $this->app->singleton(ValidationService::class);

        // Register model
        $this->app->bind(Kyc::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load infrastructure routes
        $this->loadRoutesFrom(__DIR__.'/../routes/kyc.php');

        // Publish configuration
        $this->publishes([
            __DIR__.'/../../config/kyc.php' => config_path('kyc.php'),
        ], 'kyc-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'kyc-migrations');

        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__.'/../../config/kyc.php',
            'kyc'
        );

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
