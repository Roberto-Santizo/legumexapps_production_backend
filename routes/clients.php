<?php

use App\Http\Controllers\ClientsController;
use Illuminate\Support\Facades\Route;

Route::middleware('jwt.auth')->group(function () {
    Route::post('/clients/uploadFile', [ClientsController::class, 'uploadFile']);
    Route::apiResource('/clients', ClientsController::class);
});
