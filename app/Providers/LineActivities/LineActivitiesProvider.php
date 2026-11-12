<?php

namespace App\Providers\LineActivities;

use App\Interfaces\LineActivities\LineActivitiesServiceInterface;
use App\Services\LineActivities\LineActivitiesService;
use Illuminate\Support\ServiceProvider;

class LineActivitiesProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(LineActivitiesServiceInterface::class, LineActivitiesService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
