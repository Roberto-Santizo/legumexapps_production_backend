<?php

use App\Http\Controllers\LineSkusController;
use Illuminate\Support\Facades\Route;

Route::middleware('jwt.auth')->group(function () {
    Route::apiResource('/skus', kusController::class);
    Route::apiResource('/performances', LineSkusController::class);
});
