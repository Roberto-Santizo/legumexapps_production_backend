<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::get('/check-status', [AuthController::class, 'checkstatus'])->middleware('jwt.auth');
