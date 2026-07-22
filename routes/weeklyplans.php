<?php

use App\Http\Controllers\WeeklyPlansController;
use Illuminate\Support\Facades\Route;

Route::middleware('jwt.auth')->group(function () {
    Route::apiResource('/weekly-plans', WeeklyPlansController::class);
});
