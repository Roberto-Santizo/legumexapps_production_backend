<?php

use App\Http\Controllers\SkusController;
use Illuminate\Support\Facades\Route;


Route::middleware('jwt.auth')->group(function () {
    Route::apiResource('/skus', SkusController::class);
});
