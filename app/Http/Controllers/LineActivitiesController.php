<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\LineActivities\CreateLineActivityRequest;
use App\Http\Requests\LineActivities\UpdateLineActivityRequest;
use App\Http\Resources\LineActivities\LineActivityResource;
use App\Interfaces\LineActivities\LineActivitiesServiceInterface;

class LineActivitiesController extends Controller
{
    public function activities(string $code, LineActivitiesServiceInterface $service)
    {
        try {
            $lineActivities = $service->getLineActivities($code);

            return ResponseHandler::success(LineActivityResource::collection($lineActivities), 'Actividades de la Línea Obtenidas Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    public function assignActivity(CreateLineActivityRequest $request, string $code, LineActivitiesServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $lineActivity = $service->createLineActivity($code, $data);

            return ResponseHandler::success(new LineActivityResource($lineActivity->load('activity')), 'Actividad Asignada Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    public function updateActivity(UpdateLineActivityRequest $request, string $id, LineActivitiesServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $service->updateLineActivityById($id, $data);

            return ResponseHandler::success(null, 'Actividad de la Línea Actualizada Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    public function removeActivity(string $id, LineActivitiesServiceInterface $service)
    {
        try {
            $service->deleteLineActivityById($id);

            return ResponseHandler::success(null, 'Actividad Quitada de la Línea Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
