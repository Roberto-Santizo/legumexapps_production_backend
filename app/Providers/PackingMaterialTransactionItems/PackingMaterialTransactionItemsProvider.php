<?php

namespace App\Providers\PackingMaterialTransactionItems;

use App\Interfaces\PackingMaterialTransactionItems\PackingMaterialTransactionItemsServiceInterface;
use App\Services\PackingMaterialTransactionItems\PackingMaterialTransactionItemsService;
use Illuminate\Support\ServiceProvider;

class PackingMaterialTransactionItemsProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(PackingMaterialTransactionItemsServiceInterface::class, PackingMaterialTransactionItemsService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
