<?php

namespace App\Providers\SkuRawMaterials;

use App\Interfaces\SkuRawMaterials\SkuRawMaterialsServiceInterface;
use App\Services\SkuRawMaterials\SkuRawMaterialsService;
use Illuminate\Support\ServiceProvider;

class SkuRawMaterialsProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(SkuRawMaterialsServiceInterface::class, SkuRawMaterialsService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
