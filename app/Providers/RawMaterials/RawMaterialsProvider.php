<?php

namespace App\Providers\RawMaterials;

use App\Interfaces\RawMaterials\RawMaterialsServiceInterface;
use App\Services\RawMaterials\RawMaterialsService;
use Illuminate\Support\ServiceProvider;

class RawMaterialsProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(RawMaterialsServiceInterface::class, RawMaterialsService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
