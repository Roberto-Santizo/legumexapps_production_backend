<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Resources\BiometricQueries\BiometricEmployeeResource;
use App\Http\Resources\BiometricQueries\BiometricLineEmployeeResource;
use App\Interfaces\BiometricQueries\BiometricQueriesServiceInterface;

class BiometricQueriesController extends Controller
{
    public function employees(BiometricQueriesServiceInterface $service)
    {
        try {
            $employees = $service->getAllEmployees();

            return ResponseHandler::success(BiometricEmployeeResource::collection($employees), 'Empleados Obtenidos Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    public function employeesByLineCode(string $lineCode, BiometricQueriesServiceInterface $service)
    {
        try {
            $employees = $service->getEmployeesByLineCode($lineCode);

            return ResponseHandler::success(BiometricLineEmployeeResource::collection($employees), 'Empleados Obtenidos Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
