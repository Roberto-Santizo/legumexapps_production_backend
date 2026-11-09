<?php

namespace App\Providers\BiometricQueries;

use App\Interfaces\BiometricQueries\BiometricQueriesServiceInterface;
use App\Services\BiometricQueries\BiometricQueriesService;
use Illuminate\Support\ServiceProvider;

class BiometricQueriesProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(BiometricQueriesServiceInterface::class, BiometricQueriesService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
