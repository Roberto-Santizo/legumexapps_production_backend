<?php

use App\Providers\AppServiceProvider;
use App\Providers\Auth\AuthProvider;
use App\Providers\Clients\ClientsProvider;
use App\Providers\Lines\LinesProvider;
use App\Providers\Permissions\PermissionProvider;
use App\Providers\Skus\SkusProvider;
use App\Providers\Users\UserPermissionProvider;
use App\Providers\Users\UserProvider;

return [
    AppServiceProvider::class,
    AuthProvider::class,
    ClientsProvider::class,
    LinesProvider::class,
    PermissionProvider::class,
    SkusProvider::class,
    UserPermissionProvider::class,
    UserProvider::class,
];
