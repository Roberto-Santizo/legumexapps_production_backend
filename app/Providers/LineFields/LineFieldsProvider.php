<?php

namespace App\Providers\LineFields;

use App\Interfaces\LineFields\LineFieldsServiceInterface;
use App\Services\LineFields\LineFieldsService;
use Illuminate\Support\ServiceProvider;

class LineFieldsProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(LineFieldsServiceInterface::class, LineFieldsService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
