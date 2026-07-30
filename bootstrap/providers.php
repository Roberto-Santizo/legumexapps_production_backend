<?php

use App\Providers\AppServiceProvider;
use App\Providers\Auth\AuthProvider;
use App\Providers\Clients\ClientsProvider;
use App\Providers\DraftWeeklyPlans\DraftWeeklyPlansProvider;
use App\Providers\DraftWeeklyPlanTasks\DraftWeeklyPlanTasksProvider;
use App\Providers\Lines\LinesProvider;
use App\Providers\LineSkus\LineSkusProvider;
use App\Providers\PackingMaterials\PackingMaterialsProvider;
use App\Providers\PackingMaterialTransactionItems\PackingMaterialTransactionItemsProvider;
use App\Providers\PackingMaterialTransactions\PackingMaterialTransactionsProvider;
use App\Providers\Permissions\PermissionProvider;
use App\Providers\Positions\PositionsProvider;
use App\Providers\RawMaterials\RawMaterialsProvider;
use App\Providers\SkuPackingMaterials\SkuPackingMaterialsProvider;
use App\Providers\SkuRawMaterials\SkuRawMaterialsProvider;
use App\Providers\Skus\SkusProvider;
use App\Providers\Timeouts\TimeoutsProvider;
use App\Providers\Users\UserPermissionProvider;
use App\Providers\Users\UserProvider;
use App\Providers\WeeklyPlanEmployees\WeeklyPlanEmployeesProvider;
use App\Providers\WeeklyPlans\WeeklyPlansProvider;
use App\Providers\WeeklyPlanTasks\WeeklyPlanTasksProvider;

return [
    AppServiceProvider::class,
    AuthProvider::class,
    ClientsProvider::class,
    DraftWeeklyPlansProvider::class,
    DraftWeeklyPlanTasksProvider::class,
    LineSkusProvider::class,
    LinesProvider::class,
    PackingMaterialsProvider::class,
    PackingMaterialTransactionItemsProvider::class,
    PackingMaterialTransactionsProvider::class,
    PermissionProvider::class,
    PositionsProvider::class,
    RawMaterialsProvider::class,
    SkuPackingMaterialsProvider::class,
    SkuRawMaterialsProvider::class,
    SkusProvider::class,
    TimeoutsProvider::class,
    UserPermissionProvider::class,
    UserProvider::class,
    WeeklyPlanEmployeesProvider::class,
    WeeklyPlansProvider::class,
    WeeklyPlanTasksProvider::class,
];
