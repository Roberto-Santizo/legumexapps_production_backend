<?php

use App\Http\Controllers\WeeklyPlanTasksController;
use Illuminate\Support\Facades\Route;

Route::middleware('jwt.auth')->group(function () {
    Route::apiResource('/weekly-plan-tasks', WeeklyPlanTasksController::class);
});

// FUNCTIONALITYS
Route::middleware('jwt.auth')->group(function () {
    Route::post('/weekly-plan-tasks/assignOperationDate', [WeeklyPlanTasksController::class, 'assignOperationDate']);
    Route::post('/weekly-plan-tasks/splitTask', [WeeklyPlanTasksController::class, 'splitTask']);
});
