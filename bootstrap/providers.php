<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\Auth\AuthProvider::class,
    App\Providers\Clients\ClientsProvider::class,
    App\Providers\LineSkus\LineSkusProvider::class,
    App\Providers\Lines\LinesProvider::class,
    App\Providers\PackingMaterials\PackingMaterialsProvider::class,
    App\Providers\Permissions\PermissionProvider::class,
    App\Providers\Positions\PositionsProvider::class,
    App\Providers\SkuPackingMaterials\SkuPackingMaterialsProvider::class,
    App\Providers\Skus\SkusProvider::class,
    App\Providers\Users\UserPermissionProvider::class,
    App\Providers\Users\UserProvider::class,
    App\Providers\WeeklyPlans\WeeklyPlansProvider::class,
];
