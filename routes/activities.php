<?php

use App\Http\Controllers\ActivitiesController;
use Illuminate\Support\Facades\Route;

Route::middleware('jwt.auth')->group(function () {
    Route::apiResource('/activities', ActivitiesController::class)->parameters(['activities' => 'id']);
});
