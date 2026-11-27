<?php

use App\Http\Controllers\LineSkuActivitiesController;
use App\Http\Controllers\LineSkusController;
use App\Http\Controllers\SkuPackingMaterialsController;
use App\Http\Controllers\SkuRawMaterialsController;
use App\Http\Controllers\SkusController;
use Illuminate\Support\Facades\Route;

Route::middleware('jwt.auth')->group(function () {
    Route::post('/skus/uploadFile', [SkusController::class, 'uploadFile']);
    Route::post('/performances/uploadFile', [LineSkusController::class, 'uploadFile']);
    Route::patch('/performances/{id}/toggle-status', [LineSkusController::class, 'toggleStatus']);

    Route::get('/performances/{id}/activities', [LineSkuActivitiesController::class, 'activities']);
    Route::post('/performances/{id}/activities', [LineSkuActivitiesController::class, 'assignActivity']);
    Route::patch('/line-sku-activities/{id}', [LineSkuActivitiesController::class, 'updateActivity']);
    Route::delete('/line-sku-activities/{id}', [LineSkuActivitiesController::class, 'removeActivity']);

    Route::apiResource('/skus', SkusController::class);
    Route::apiResource('/performances', LineSkusController::class);
    Route::apiResource('/sku-packing-materials', SkuPackingMaterialsController::class);
    Route::apiResource('/sku-raw-materials', SkuRawMaterialsController::class);
});
