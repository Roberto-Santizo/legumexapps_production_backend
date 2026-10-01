<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Resources\WeeklyPlanEmployees\WeeklyPlanEmployeeResource;
use App\Http\Resources\WeeklyPlanTaskEmployees\WeeklyPlanTaskEmployeeResource;
use App\Interfaces\WeeklyPlanTaskEmployees\WeeklyPlanTaskEmployeesServiceInterface;

class WeeklyPlanTaskEmployeesController extends Controller
{
    public function availableEmployees(string $id, WeeklyPlanTaskEmployeesServiceInterface $service)
    {
        try {
            $candidates = $service->getAvailableEmployees($id);

            return ResponseHandler::success(WeeklyPlanEmployeeResource::collection($candidates), 'Candidatos Obtenidos Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    public function employees(string $id, WeeklyPlanTaskEmployeesServiceInterface $service)
    {
        try {
            $assignments = $service->getTaskEmployees($id);

            return ResponseHandler::success(WeeklyPlanTaskEmployeeResource::collection($assignments), 'Personal Asignado Obtenido Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
