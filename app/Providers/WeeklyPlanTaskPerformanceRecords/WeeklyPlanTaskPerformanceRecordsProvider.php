<?php

namespace App\Providers\WeeklyPlanTaskPerformanceRecords;

use App\Interfaces\WeeklyPlanTaskPerformanceRecords\WeeklyPlanTaskPerformanceRecordsServiceInterface;
use App\Services\WeeklyPlanTaskPerformanceRecords\WeeklyPlanTaskPerformanceRecordsService;
use Illuminate\Support\ServiceProvider;

class WeeklyPlanTaskPerformanceRecordsProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(WeeklyPlanTaskPerformanceRecordsServiceInterface::class, WeeklyPlanTaskPerformanceRecordsService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
