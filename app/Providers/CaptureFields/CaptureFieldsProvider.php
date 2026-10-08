<?php

namespace App\Providers\CaptureFields;

use App\Interfaces\CaptureFields\CaptureFieldsServiceInterface;
use App\Services\CaptureFields\CaptureFieldsService;
use Illuminate\Support\ServiceProvider;

class CaptureFieldsProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(CaptureFieldsServiceInterface::class, CaptureFieldsService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
