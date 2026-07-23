<?php

namespace App\Providers\WeeklyPlanEmployees;

use App\Interfaces\WeeklyPlanEmployees\WeeklyPlanEmployeesServiceInterface;
use App\Services\WeeklyPlanEmployees\WeeklyPlanEmployeesService;
use Illuminate\Support\ServiceProvider;

class WeeklyPlanEmployeesProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(WeeklyPlanEmployeesServiceInterface::class, WeeklyPlanEmployeesService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
