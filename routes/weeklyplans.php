<?php

use App\Http\Controllers\WeeklyPlansController;
use Illuminate\Support\Facades\Route;

Route::middleware('jwt.auth')->group(function () {
    Route::apiResource('/weekly-plans', WeeklyPlansController::class);
});

// FUNCTIONALITYS
Route::middleware('jwt.auth')->group(function () {
    Route::get('/weekly-plans/tasksForCalendar/{id}', [WeeklyPlansController::class, 'getTasksForCalendar']);
    Route::get('/weekly-plans/summaryToday/{id}', [WeeklyPlansController::class, 'getWeeklyPlanSummaryToday']);
});
