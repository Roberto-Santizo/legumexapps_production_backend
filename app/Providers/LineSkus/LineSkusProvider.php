<?php

namespace App\Providers\LineSkus;

use App\Interfaces\LineSkus\LineSkusServiceInterface;
use App\Services\LineSkus\LineSkusService;
use Illuminate\Support\ServiceProvider;

class LineSkusProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(LineSkusServiceInterface::class, LineSkusService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
