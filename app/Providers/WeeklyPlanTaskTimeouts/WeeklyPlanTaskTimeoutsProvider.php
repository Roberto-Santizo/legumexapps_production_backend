<?php

namespace App\Providers\WeeklyPlanTaskTimeouts;

use App\Interfaces\WeeklyPlanTaskTimeouts\WeeklyPlanTaskTimeoutsServiceInterface;
use App\Services\WeeklyPlanTaskTimeouts\WeeklyPlanTaskTimeoutsService;
use Illuminate\Support\ServiceProvider;

class WeeklyPlanTaskTimeoutsProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(WeeklyPlanTaskTimeoutsServiceInterface::class, WeeklyPlanTaskTimeoutsService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
