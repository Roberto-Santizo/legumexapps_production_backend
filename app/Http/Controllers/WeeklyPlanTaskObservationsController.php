<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\WeeklyPlanTaskObservations\CreateWeeklyPlanTaskObservationRequest;
use App\Http\Requests\WeeklyPlanTaskObservations\UpdateWeeklyPlanTaskObservationRequest;
use App\Http\Resources\WeeklyPlanTaskObservations\WeeklyPlanTaskObservationResource;
use App\Interfaces\WeeklyPlanTaskObservations\WeeklyPlanTaskObservationsServiceInterface;
use Illuminate\Http\Request;

class WeeklyPlanTaskObservationsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, WeeklyPlanTaskObservationsServiceInterface $service)
    {
        try {
            $observations = $service->getWeeklyPlanTaskObservations($request);

            return ResponseHandler::success(WeeklyPlanTaskObservationResource::collection($observations), 'Observaciones Obtenidas Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateWeeklyPlanTaskObservationRequest $request, WeeklyPlanTaskObservationsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $result = $service->createWeeklyPlanTaskObservation($data);

            return ResponseHandler::success($result, 'Observación Creada Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, WeeklyPlanTaskObservationsServiceInterface $service)
    {
        try {
            $observation = $service->getWeeklyPlanTaskObservationById($id);

            return ResponseHandler::success(new WeeklyPlanTaskObservationResource($observation), 'Observación Obtenida Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWeeklyPlanTaskObservationRequest $request, string $id, WeeklyPlanTaskObservationsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $result = $service->updateWeeklyPlanTaskObservationById($data, $id);

            return ResponseHandler::success($result, 'Observación Actualizada Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, WeeklyPlanTaskObservationsServiceInterface $service)
    {
        try {
            $result = $service->deleteWeeklyPlanTaskObservationById($id);

            return ResponseHandler::success($result, 'Observación Eliminada Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
