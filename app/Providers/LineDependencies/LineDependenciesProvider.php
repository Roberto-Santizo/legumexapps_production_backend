<?php

namespace App\Providers\LineDependencies;

use App\Interfaces\LineDependencies\LineDependenciesServiceInterface;
use App\Services\LineDependencies\LineDependenciesService;
use Illuminate\Support\ServiceProvider;

class LineDependenciesProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(LineDependenciesServiceInterface::class, LineDependenciesService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
