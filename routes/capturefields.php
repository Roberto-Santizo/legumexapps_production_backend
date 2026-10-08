<?php

use App\Http\Controllers\CaptureFieldsController;
use Illuminate\Support\Facades\Route;

Route::middleware('jwt.auth')->group(function () {
    Route::apiResource('/capture-fields', CaptureFieldsController::class)->parameters(['capture-fields' => 'id']);
});
