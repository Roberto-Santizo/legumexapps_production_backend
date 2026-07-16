<?php

use App\Http\Controllers\LinesController;
use Illuminate\Support\Facades\Route;


Route::middleware('jwt.auth')->group(function () {
    Route::apiResource('/lines', LinesController::class);
});
