<?php

namespace App\Providers\WeeklyPlanTaskEmployees;

use App\Interfaces\WeeklyPlanTaskEmployees\WeeklyPlanTaskEmployeesServiceInterface;
use App\Services\WeeklyPlanTaskEmployees\WeeklyPlanTaskEmployeesService;
use Illuminate\Support\ServiceProvider;

class WeeklyPlanTaskEmployeesProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(WeeklyPlanTaskEmployeesServiceInterface::class, WeeklyPlanTaskEmployeesService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
