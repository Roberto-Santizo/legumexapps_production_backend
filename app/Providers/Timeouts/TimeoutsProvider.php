<?php

namespace App\Providers\Timeouts;

use App\Interfaces\Timeouts\TimeoutsServiceInterface;
use App\Services\Timeouts\TimeoutsService;
use Illuminate\Support\ServiceProvider;

class TimeoutsProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(TimeoutsServiceInterface::class, TimeoutsService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
