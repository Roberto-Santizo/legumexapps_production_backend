<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\LineSkuActivities\CreateLineSkuActivityRequest;
use App\Http\Requests\LineSkuActivities\UpdateLineSkuActivityRequest;
use App\Http\Resources\LineSkuActivities\LineSkuActivityResource;
use App\Interfaces\LineSkuActivities\LineSkuActivitiesServiceInterface;

class LineSkuActivitiesController extends Controller
{
    public function activities(string $id, LineSkuActivitiesServiceInterface $service)
    {
        try {
            $lineSkuActivities = $service->getLineSkuActivities($id);

            return ResponseHandler::success(LineSkuActivityResource::collection($lineSkuActivities), 'Actividades del SKU de Línea Obtenidas Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    public function assignActivity(CreateLineSkuActivityRequest $request, string $id, LineSkuActivitiesServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $lineSkuActivity = $service->createLineSkuActivity($id, $data);

            return ResponseHandler::success(new LineSkuActivityResource($lineSkuActivity->load('activity')), 'Actividad Asignada Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    public function updateActivity(UpdateLineSkuActivityRequest $request, string $id, LineSkuActivitiesServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $service->updateLineSkuActivityById($id, $data);

            return ResponseHandler::success(null, 'Actividad del SKU de Línea Actualizada Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    public function removeActivity(string $id, LineSkuActivitiesServiceInterface $service)
    {
        try {
            $service->deleteLineSkuActivityById($id);

            return ResponseHandler::success(null, 'Actividad Quitada del SKU de Línea Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
