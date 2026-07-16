<?php

namespace App\Providers\Lines;

use App\Interfaces\Lines\LinesServiceInterface;
use App\Services\Lines\LinesService;
use Illuminate\Support\ServiceProvider;

class LinesProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(LinesServiceInterface::class, LinesService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
