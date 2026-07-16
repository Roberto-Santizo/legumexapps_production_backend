<?php

namespace App\Providers\Users;

use App\Interfaces\Users\UserPermissionServiceInterface;
use App\Services\Users\UserPermissionService;
use Illuminate\Support\ServiceProvider;

class UserPermissionProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(UserPermissionServiceInterface::class, UserPermissionService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
