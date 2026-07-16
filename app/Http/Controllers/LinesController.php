<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\Lines\CreateLineRequest;
use App\Http\Requests\Lines\UpdateLineRequest;
use App\Http\Resources\Lines\LineResource;
use App\Http\Resources\Lines\PaginatedLinesResource;
use App\Interfaces\Lines\LinesServiceInterface;
use Illuminate\Http\Request;

class LinesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, LinesServiceInterface $service)
    {
        try {
            $limit = $request->query('limit');
            $result = $service->getLines($limit);

            $response = $limit ? new PaginatedLinesResource($result) : LineResource::collection($result);

            return ResponseHandler::success($response, 'Líneas Obtenidas Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateLineRequest $request, LinesServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $result = $service->createLine($data);

            return ResponseHandler::success($result, 'Línea Creada Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, LinesServiceInterface $service)
    {
        try {
            $result = $service->getLineByCode($id);

            return ResponseHandler::success(new LineResource($result), 'Línea Obtenida Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateLineRequest $request, string $id, LinesServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $result = $service->updateLineById($id, $data);

            return ResponseHandler::success($result, 'Línea Actualizada Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, LinesServiceInterface $service)
    {
        try {
            $result = $service->deleteLineById($id);

            return ResponseHandler::success(new LineResource($result), 'Línea Eliminada Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
