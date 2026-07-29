<?php

use App\Http\Controllers\DraftWeeklyPlansController;
use Illuminate\Support\Facades\Route;

Route::middleware('jwt.auth')->group(function () {
    Route::apiResource('/draft-weekly-plans', DraftWeeklyPlansController::class);
});

// FUNCTIONALITYS
Route::middleware('jwt.auth')->group(function () {
    Route::post('/draft-weekly-plans/{id}/confirm', [DraftWeeklyPlansController::class, 'confirm']);
});
