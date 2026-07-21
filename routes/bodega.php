<?php

use App\Http\Controllers\PackingMaterialsController;
use Illuminate\Support\Facades\Route;

Route::middleware('jwt.auth')->group(function () {
    Route::apiResource('/packing-materials', PackingMaterialsController::class);
});
