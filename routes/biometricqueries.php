<?php

use App\Http\Controllers\BiometricQueriesController;
use Illuminate\Support\Facades\Route;

Route::middleware('jwt.auth')->group(function () {
    // FUNCTIONALITYS
    Route::get('/biometric-queries/employees', [BiometricQueriesController::class, 'employees']);
    Route::get('/biometric-queries/employeesByLineCode/{lineCode}', [BiometricQueriesController::class, 'employeesByLineCode']);
});
