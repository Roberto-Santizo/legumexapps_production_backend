<?php

namespace App\Providers\WeeklyPlanTaskObservations;

use App\Interfaces\WeeklyPlanTaskObservations\WeeklyPlanTaskObservationsServiceInterface;
use App\Services\WeeklyPlanTaskObservations\WeeklyPlanTaskObservationsService;
use Illuminate\Support\ServiceProvider;

class WeeklyPlanTaskObservationsProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(WeeklyPlanTaskObservationsServiceInterface::class, WeeklyPlanTaskObservationsService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
