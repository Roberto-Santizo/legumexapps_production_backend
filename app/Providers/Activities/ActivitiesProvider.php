<?php

namespace App\Providers\Activities;

use App\Interfaces\Activities\ActivitiesServiceInterface;
use App\Services\Activities\ActivitiesService;
use Illuminate\Support\ServiceProvider;

class ActivitiesProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(ActivitiesServiceInterface::class, ActivitiesService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
