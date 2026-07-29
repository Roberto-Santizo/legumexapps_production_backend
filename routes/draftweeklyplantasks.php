<?php

use App\Http\Controllers\DraftWeeklyPlanTasksController;
use Illuminate\Support\Facades\Route;

Route::middleware('jwt.auth')->group(function () {
    Route::apiResource('/draft-weekly-plan-tasks', DraftWeeklyPlanTasksController::class);
});
