<?php

use App\Providers\AppServiceProvider;
use App\Providers\Auth\AuthProvider;
use App\Providers\Permissions\PermissionProvider;
use App\Providers\Users\UserPermissionProvider;
use App\Providers\Users\UserProvider;

return [
    AppServiceProvider::class,
    AuthProvider::class,
    UserProvider::class,
    UserPermissionProvider::class,
    PermissionProvider::class,
];
