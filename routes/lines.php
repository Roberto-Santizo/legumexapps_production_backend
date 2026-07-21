<?php

use App\Http\Controllers\LinesController;
use App\Http\Controllers\PositionsController;
use Illuminate\Support\Facades\Route;

Route::middleware('jwt.auth')->group(function () {
    Route::apiResource('/lines',        LinesController::class);
    Route::apiResource('/positions',    PositionsController::class);
});
