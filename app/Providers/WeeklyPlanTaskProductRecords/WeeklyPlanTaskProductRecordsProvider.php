<?php

namespace App\Providers\WeeklyPlanTaskProductRecords;

use App\Interfaces\WeeklyPlanTaskProductRecords\WeeklyPlanTaskProductRecordsServiceInterface;
use App\Services\WeeklyPlanTaskProductRecords\WeeklyPlanTaskProductRecordsService;
use Illuminate\Support\ServiceProvider;

class WeeklyPlanTaskProductRecordsProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(WeeklyPlanTaskProductRecordsServiceInterface::class, WeeklyPlanTaskProductRecordsService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
