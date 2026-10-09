<?php

use App\Http\Controllers\WeeklyPlanTaskEmployeesController;
use App\Http\Controllers\WeeklyPlanTaskLotRecordsController;
use App\Http\Controllers\WeeklyPlanTaskObservationsController;
use App\Http\Controllers\WeeklyPlanTaskPerformanceRecordsController;
use App\Http\Controllers\WeeklyPlanTasksController;
use App\Http\Controllers\WeeklyPlanTaskTimeoutsController;
use Illuminate\Support\Facades\Route;

Route::middleware('jwt.auth')->group(function () {
    Route::apiResource('/weekly-plan-tasks', WeeklyPlanTasksController::class);
    Route::apiResource('/weekly-plan-task-observations', WeeklyPlanTaskObservationsController::class);
    Route::apiResource('/weekly-plan-task-performance-records', WeeklyPlanTaskPerformanceRecordsController::class)
        ->parameters(['weekly-plan-task-performance-records' => 'id']);
    Route::apiResource('/weekly-plan-task-lot-records', WeeklyPlanTaskLotRecordsController::class)
        ->parameters(['weekly-plan-task-lot-records' => 'id']);
});

// FUNCTIONALITYS
Route::middleware('jwt.auth')->group(function () {
    Route::post('/weekly-plan-tasks/assignOperationDate', [WeeklyPlanTasksController::class, 'assignOperationDate']);
    Route::post('/weekly-plan-tasks/splitTask', [WeeklyPlanTasksController::class, 'splitTask']);
    Route::get('/weekly-plan-tasks/{id}/packingMaterialItems', [WeeklyPlanTasksController::class, 'packingMaterialItems']);
    Route::post('/weekly-plan-tasks/{id}/start', [WeeklyPlanTasksController::class, 'start']);
    Route::post('/weekly-plan-tasks/{id}/end', [WeeklyPlanTasksController::class, 'end']);
    Route::get('/weekly-plan-tasks/{id}/availableEmployees', [WeeklyPlanTaskEmployeesController::class, 'availableEmployees']);
    Route::get('/weekly-plan-tasks/{id}/employees', [WeeklyPlanTaskEmployeesController::class, 'employees']);
    Route::post('/weekly-plan-tasks/{id}/confirmEmployees', [WeeklyPlanTaskEmployeesController::class, 'confirmEmployees']);
    Route::post('/weekly-plan-tasks/{id}/addEmployee', [WeeklyPlanTaskEmployeesController::class, 'addEmployee']);
    Route::patch('/weekly-plan-task-employees/{id}/replace', [WeeklyPlanTaskEmployeesController::class, 'replaceEmployee']);
    Route::delete('/weekly-plan-task-employees/{id}', [WeeklyPlanTaskEmployeesController::class, 'removeEmployee']);
    Route::get('/weekly-plan-tasks/{id}/timeouts', [WeeklyPlanTaskTimeoutsController::class, 'timeouts']);
    Route::post('/weekly-plan-tasks/{id}/startTimeout', [WeeklyPlanTaskTimeoutsController::class, 'startTimeout']);
    Route::post('/weekly-plan-task-timeouts/{id}/end', [WeeklyPlanTaskTimeoutsController::class, 'endTimeout']);
    Route::patch('/weekly-plan-task-timeouts/{id}', [WeeklyPlanTaskTimeoutsController::class, 'updateTimeout']);
    Route::delete('/weekly-plan-task-timeouts/{id}', [WeeklyPlanTaskTimeoutsController::class, 'deleteTimeout']);
});
