<?php

use App\Http\Controllers\WeeklyPlanTasksController;
use Illuminate\Support\Facades\Route;

Route::middleware('jwt.auth')->group(function () {
    Route::apiResource('/weekly-plan-tasks', WeeklyPlanTasksController::class);
});
