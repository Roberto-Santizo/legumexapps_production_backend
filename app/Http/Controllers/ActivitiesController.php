<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\Activities\CreateActivityRequest;
use App\Http\Requests\Activities\UpdateActivityRequest;
use App\Http\Resources\Activities\ActivityResource;
use App\Http\Resources\Activities\PaginatedActivitiesResource;
use App\Interfaces\Activities\ActivitiesServiceInterface;
use Illuminate\Http\Request;

class ActivitiesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, ActivitiesServiceInterface $service)
    {
        try {
            $limit = $request->query('limit');
            $response = $service->getActivities($limit, $request);

            $data = $limit ? new PaginatedActivitiesResource($response) : ActivityResource::collection($response);

            return ResponseHandler::success($data, 'Actividades Obtenidas Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateActivityRequest $request, ActivitiesServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $response = $service->createActivity($data);

            return ResponseHandler::success(new ActivityResource($response), 'Actividad Creada Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, ActivitiesServiceInterface $service)
    {
        try {
            $response = $service->getActivityById($id);

            return ResponseHandler::success(new ActivityResource($response), 'Actividad Obtenida Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateActivityRequest $request, string $id, ActivitiesServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $service->updateActivityById($id, $data);

            return ResponseHandler::success(null, 'Actividad Actualizada Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, ActivitiesServiceInterface $service)
    {
        try {
            $service->deleteActivityById($id);

            return ResponseHandler::success(null, 'Actividad Eliminada Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
