<?php

namespace App\Providers\Clients;

use App\Interfaces\Clients\ClientsServiceInterface;
use App\Services\Clients\ClientsService;
use Illuminate\Support\ServiceProvider;

class ClientsProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(ClientsServiceInterface::class, ClientsService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
