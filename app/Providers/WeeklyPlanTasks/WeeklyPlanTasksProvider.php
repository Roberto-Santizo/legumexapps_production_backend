<?php

namespace App\Providers\WeeklyPlanTasks;

use App\Interfaces\WeeklyPlanTasks\WeeklyPlanTasksServiceInterface;
use App\Services\WeeklyPlanTasks\WeeklyPlanTasksService;
use Illuminate\Support\ServiceProvider;

class WeeklyPlanTasksProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(WeeklyPlanTasksServiceInterface::class, WeeklyPlanTasksService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
