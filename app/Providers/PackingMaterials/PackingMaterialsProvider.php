<?php

namespace App\Providers\PackingMaterials;

use App\Interfaces\PackingMaterials\PackingMaterialsServiceInterface;
use App\Services\PackingMaterials\PackingMaterialsService;
use Illuminate\Support\ServiceProvider;

class PackingMaterialsProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(PackingMaterialsServiceInterface::class, PackingMaterialsService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
