<?php

use App\Http\Controllers\DraftWeeklyPlansController;
use Illuminate\Support\Facades\Route;

Route::middleware('jwt.auth')->group(function () {
    Route::apiResource('/draft-weekly-plans', DraftWeeklyPlansController::class);
});

// FUNCTIONALITYS
Route::middleware('jwt.auth')->group(function () {
    Route::post('/draft-weekly-plans/{id}/confirm', [DraftWeeklyPlansController::class, 'confirm']);
    Route::get('/draft-weekly-plans/{id}/hoursPerLine', [DraftWeeklyPlansController::class, 'hoursPerLine']);
    Route::get('/draft-weekly-plans/{id}/packingMaterialNecessity', [DraftWeeklyPlansController::class, 'getPackingMaterialNecessity']);
    Route::get('/draft-weekly-plans/{id}/rawMaterialNecessity', [DraftWeeklyPlansController::class, 'getRawMaterialNecessity']);
});
