<?php

namespace App\Providers\WeeklyPlans;

use App\Interfaces\WeeklyPlans\WeeklyPlansServiceInterface;
use App\Services\WeeklyPlans\WeeklyPlansService;
use Illuminate\Support\ServiceProvider;

class WeeklyPlansProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(WeeklyPlansServiceInterface::class, WeeklyPlansService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
