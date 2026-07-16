<?php

use App\Http\Controllers\Permissions\PermissionController;
use Illuminate\Support\Facades\Route;

Route::middleware('jwt.auth')->group(function () {
    Route::apiResource('/permissions', PermissionController::class)->middleware('admin');
});
