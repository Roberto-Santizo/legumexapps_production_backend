<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\Timeouts\CreateTimeoutRequest;
use App\Http\Requests\Timeouts\UpdateTimeoutRequest;
use App\Http\Resources\Timeouts\PaginatedTimeoutsResource;
use App\Http\Resources\Timeouts\TimeoutResource;
use App\Interfaces\Timeouts\TimeoutsServiceInterface;
use Illuminate\Http\Request;

class TimeoutsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, TimeoutsServiceInterface $service)
    {
        try {
            $limit = $request->query('limit');
            $response = $service->getTimeouts($limit);

            $data = $limit ? new PaginatedTimeoutsResource($response) : TimeoutResource::collection($response);

            return ResponseHandler::success($data, 'Tiempos Muertos Obtenidos Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateTimeoutRequest $request, TimeoutsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $response = $service->createTimeout($data);

            return ResponseHandler::success($response, 'Tiempo Muerto Creado Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, TimeoutsServiceInterface $service)
    {
        try {
            $response = $service->getTimeoutById($id);

            return ResponseHandler::success(new TimeoutResource($response), 'Tiempo Muerto Obtenido Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTimeoutRequest $request, string $id, TimeoutsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $response = $service->updateTimeoutById($id, $data);

            return ResponseHandler::success($response, 'Tiempo Muerto Actualizado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, TimeoutsServiceInterface $service)
    {
        try {
            $response = $service->deleteTimeoutById($id);

            return ResponseHandler::success($response, 'Tiempo Muerto Eliminado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
