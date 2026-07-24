<?php

use App\Http\Controllers\PackingMaterialsController;
use App\Http\Controllers\RawMaterialsController;
use Illuminate\Support\Facades\Route;

Route::middleware('jwt.auth')->group(function () {
    Route::apiResource('/packing-materials',    PackingMaterialsController::class);
    Route::apiResource('/raw-materials',        RawMaterialsController::class);
});
