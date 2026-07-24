<?php

use App\Http\Controllers\TimeoutsController;
use Illuminate\Support\Facades\Route;

Route::middleware('jwt.auth')->group(function () {
    Route::apiResource('/timeouts', TimeoutsController::class);
});
