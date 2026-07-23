<?php

use App\Providers\AppServiceProvider;
use App\Providers\Auth\AuthProvider;
use App\Providers\Clients\ClientsProvider;
use App\Providers\Lines\LinesProvider;
use App\Providers\LineSkus\LineSkusProvider;
use App\Providers\PackingMaterials\PackingMaterialsProvider;
use App\Providers\Permissions\PermissionProvider;
use App\Providers\Positions\PositionsProvider;
use App\Providers\SkuPackingMaterials\SkuPackingMaterialsProvider;
use App\Providers\Skus\SkusProvider;
use App\Providers\Users\UserPermissionProvider;
use App\Providers\Users\UserProvider;
use App\Providers\WeeklyPlanEmployees\WeeklyPlanEmployeesProvider;
use App\Providers\WeeklyPlans\WeeklyPlansProvider;

return [
    AppServiceProvider::class,
    AuthProvider::class,
    ClientsProvider::class,
    LineSkusProvider::class,
    LinesProvider::class,
    PackingMaterialsProvider::class,
    PermissionProvider::class,
    PositionsProvider::class,
    SkuPackingMaterialsProvider::class,
    SkusProvider::class,
    UserPermissionProvider::class,
    UserProvider::class,
    WeeklyPlanEmployeesProvider::class,
    WeeklyPlansProvider::class,
];
