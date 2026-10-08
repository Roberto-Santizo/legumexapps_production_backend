<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\WeeklyPlanTaskTimeouts\EndWeeklyPlanTaskTimeoutRequest;
use App\Http\Requests\WeeklyPlanTaskTimeouts\StartWeeklyPlanTaskTimeoutRequest;
use App\Http\Requests\WeeklyPlanTaskTimeouts\UpdateWeeklyPlanTaskTimeoutRequest;
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

    public function startTimeout(StartWeeklyPlanTaskTimeoutRequest $request, string $id, WeeklyPlanTaskTimeoutsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $timeout = $service->startTimeout($id, $data);

            return ResponseHandler::success(new WeeklyPlanTaskTimeoutResource($timeout->load(['timeout', 'user'])), 'Tiempo Muerto Iniciado Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    public function endTimeout(EndWeeklyPlanTaskTimeoutRequest $request, string $id, WeeklyPlanTaskTimeoutsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $timeout = $service->endTimeout($id, $data);

            return ResponseHandler::success(new WeeklyPlanTaskTimeoutResource($timeout->load(['timeout', 'user'])), 'Tiempo Muerto Finalizado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    public function updateTimeout(UpdateWeeklyPlanTaskTimeoutRequest $request, string $id, WeeklyPlanTaskTimeoutsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $service->updateTimeout($id, $data);

            return ResponseHandler::success(null, 'Tiempo Muerto Actualizado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    public function deleteTimeout(string $id, WeeklyPlanTaskTimeoutsServiceInterface $service)
    {
        try {
            $service->deleteTimeout($id);

            return ResponseHandler::success(null, 'Tiempo Muerto Eliminado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
