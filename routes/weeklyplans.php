<?php

use App\Http\Controllers\WeeklyPlanEmployeesController;
use App\Http\Controllers\WeeklyPlansController;
use Illuminate\Support\Facades\Route;

Route::middleware('jwt.auth')->group(function () {
    Route::apiResource('/weekly-plans', WeeklyPlansController::class);
    Route::apiResource('/weekly-plan-employees', WeeklyPlanEmployeesController::class);
});

// FUNCTIONALITYS
Route::middleware('jwt.auth')->group(function () {
    Route::post('/weekly-plan-employees/uploadFile', [WeeklyPlanEmployeesController::class, 'uploadFile']);
});
