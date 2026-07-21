<?php

namespace App\Providers\Positions;

use App\Interfaces\Positions\PositionsServiceInterface;
use App\Services\Positions\PositionsService;
use Illuminate\Support\ServiceProvider;

class PositionsProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(PositionsServiceInterface::class, PositionsService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
