<?php

namespace App\Providers\Permissions;

use App\Interfaces\Permissions\PermissionServiceInterface;
use App\Services\Permissions\PermissionService;
use Illuminate\Support\ServiceProvider;

class PermissionProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(PermissionServiceInterface::class, PermissionService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
