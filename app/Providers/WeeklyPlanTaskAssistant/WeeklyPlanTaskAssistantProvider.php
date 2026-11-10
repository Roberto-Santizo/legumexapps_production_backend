<?php

namespace App\Providers\WeeklyPlanTaskAssistant;

use App\Interfaces\WeeklyPlanTaskAssistant\WeeklyPlanTaskAssistantServiceInterface;
use App\Services\WeeklyPlanTaskAssistant\WeeklyPlanTaskAssistantService;
use Illuminate\Support\ServiceProvider;

class WeeklyPlanTaskAssistantProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(WeeklyPlanTaskAssistantServiceInterface::class, WeeklyPlanTaskAssistantService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
