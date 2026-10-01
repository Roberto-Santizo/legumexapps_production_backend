<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Resources\WeeklyPlanEmployees\WeeklyPlanEmployeeResource;
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
}
