<?php

namespace App\Providers\DraftWeeklyPlanTasks;

use App\Interfaces\DraftWeeklyPlanTasks\DraftWeeklyPlanTasksServiceInterface;
use App\Services\DraftWeeklyPlanTasks\DraftWeeklyPlanTasksService;
use Illuminate\Support\ServiceProvider;

class DraftWeeklyPlanTasksProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(DraftWeeklyPlanTasksServiceInterface::class, DraftWeeklyPlanTasksService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
