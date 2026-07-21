<?php

namespace App\Providers\SkuPackingMaterials;

use App\Interfaces\SkuPackingMaterials\SkuPackingMaterialsServiceInterface;
use App\Services\SkuPackingMaterials\SkuPackingMaterialsService;
use Illuminate\Support\ServiceProvider;

class SkuPackingMaterialsProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(SkuPackingMaterialsServiceInterface::class, SkuPackingMaterialsService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
