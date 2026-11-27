<?php

namespace App\Providers\LineSkuActivities;

use App\Interfaces\LineSkuActivities\LineSkuActivitiesServiceInterface;
use App\Services\LineSkuActivities\LineSkuActivitiesService;
use Illuminate\Support\ServiceProvider;

class LineSkuActivitiesProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(LineSkuActivitiesServiceInterface::class, LineSkuActivitiesService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
