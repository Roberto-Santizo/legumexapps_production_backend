<?php

namespace App\Providers\WeeklyPlanTaskLotRecords;

use App\Interfaces\WeeklyPlanTaskLotRecords\WeeklyPlanTaskLotRecordsServiceInterface;
use App\Services\WeeklyPlanTaskLotRecords\WeeklyPlanTaskLotRecordsService;
use Illuminate\Support\ServiceProvider;

class WeeklyPlanTaskLotRecordsProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(WeeklyPlanTaskLotRecordsServiceInterface::class, WeeklyPlanTaskLotRecordsService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
