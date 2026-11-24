<?php

namespace App\Providers\DailyLineReports;

use App\Interfaces\DailyLineReports\DailyLineReportsServiceInterface;
use App\Services\DailyLineReports\DailyLineReportsService;
use Illuminate\Support\ServiceProvider;

class DailyLineReportsProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(DailyLineReportsServiceInterface::class, DailyLineReportsService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
