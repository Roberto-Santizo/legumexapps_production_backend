<?php

namespace App\Providers\PackingMaterialTransactions;

use App\Interfaces\PackingMaterialTransactions\PackingMaterialTransactionsServiceInterface;
use App\Services\PackingMaterialTransactions\PackingMaterialTransactionsService;
use Illuminate\Support\ServiceProvider;

class PackingMaterialTransactionsProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(PackingMaterialTransactionsServiceInterface::class, PackingMaterialTransactionsService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
