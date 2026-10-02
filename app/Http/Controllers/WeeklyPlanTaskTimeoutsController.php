<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Resources\WeeklyPlanTaskTimeouts\WeeklyPlanTaskTimeoutResource;
use App\Interfaces\WeeklyPlanTaskTimeouts\WeeklyPlanTaskTimeoutsServiceInterface;

class WeeklyPlanTaskTimeoutsController extends Controller
{
    public function timeouts(string $id, WeeklyPlanTaskTimeoutsServiceInterface $service)
    {
        try {
            $timeouts = $service->getTaskTimeouts($id);

            return ResponseHandler::success(WeeklyPlanTaskTimeoutResource::collection($timeouts), 'Tiempos Muertos Obtenidos Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
