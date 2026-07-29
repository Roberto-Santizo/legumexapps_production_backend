<?php

namespace App\Providers\DraftWeeklyPlans;

use App\Interfaces\DraftWeeklyPlans\DraftWeeklyPlansServiceInterface;
use App\Services\DraftWeeklyPlans\DraftWeeklyPlansService;
use Illuminate\Support\ServiceProvider;

class DraftWeeklyPlansProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(DraftWeeklyPlansServiceInterface::class, DraftWeeklyPlansService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
