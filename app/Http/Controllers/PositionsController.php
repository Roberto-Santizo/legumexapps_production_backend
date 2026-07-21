<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\Positions\CreatePositionRequest;
use App\Http\Requests\Positions\UpdatePositionRequest;
use App\Http\Resources\Positions\PaginatedPositionsResource;
use App\Http\Resources\Positions\PositionResource;
use App\Interfaces\Positions\PositionsServiceInterface;
use Illuminate\Http\Request;

class PositionsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, PositionsServiceInterface $service)
    {
        try {
            $limit = $request->query('limit');
            $positions = $service->getPositions($limit, $request);

            $data = $limit ? new PaginatedPositionsResource($positions) : PositionResource::collection($positions);

            return ResponseHandler::success($data, 'Puestos Obtenidos Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreatePositionRequest $request, PositionsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $result = $service->createPosition($data);

            return ResponseHandler::success($result, 'Puesto Creado Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, PositionsServiceInterface $service)
    {
        try {
            $position = $service->getPositionById($id);

            return ResponseHandler::success(new PositionResource($position), 'Puesto Obtenido Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePositionRequest $request, string $id, PositionsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $result = $service->updatePositionById($data, $id);

            return ResponseHandler::success($result, 'Puesto Actualizado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, PositionsServiceInterface $service)
    {
        try {
            $result = $service->deletePositionById($id);

            return ResponseHandler::success($result, 'Puesto Eliminado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
