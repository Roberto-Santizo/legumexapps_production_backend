<?php

namespace App\Providers\Skus;

use App\Interfaces\Skus\SkusServiceInterface;
use App\Services\Skus\SkusService;
use Illuminate\Support\ServiceProvider;

class SkusProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(SkusServiceInterface::class, SkusService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
