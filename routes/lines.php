<?php

use App\Http\Controllers\LineActivitiesController;
use App\Http\Controllers\LineDependenciesController;
use App\Http\Controllers\LineFieldsController;
use App\Http\Controllers\LinesController;
use App\Http\Controllers\PositionsController;
use Illuminate\Support\Facades\Route;

Route::middleware('jwt.auth')->group(function () {
    Route::apiResource('/lines', LinesController::class);
    Route::apiResource('/positions', PositionsController::class);
    Route::apiResource('/line-dependencies', LineDependenciesController::class);

    Route::post('/lines/uploadFile', [LinesController::class, 'uploadFile']);
    Route::post('/positions/uploadFile', [PositionsController::class, 'uploadFile']);
});

// FUNCTIONALITYS
Route::middleware('jwt.auth')->group(function () {
    Route::get('/lines/{code}/fields', [LineFieldsController::class, 'fields']);
    Route::post('/lines/{code}/fields', [LineFieldsController::class, 'assignField']);
    Route::patch('/line-fields/{id}', [LineFieldsController::class, 'updateField']);
    Route::delete('/line-fields/{id}', [LineFieldsController::class, 'removeField']);

    Route::get('/lines/{code}/activities', [LineActivitiesController::class, 'activities']);
    Route::post('/lines/{code}/activities', [LineActivitiesController::class, 'assignActivity']);
    Route::patch('/line-activities/{id}', [LineActivitiesController::class, 'updateActivity']);
    Route::delete('/line-activities/{id}', [LineActivitiesController::class, 'removeActivity']);
});
