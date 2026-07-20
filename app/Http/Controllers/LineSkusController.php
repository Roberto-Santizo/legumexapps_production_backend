<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\LineSkus\CreateLineSkuRequest;
use App\Http\Requests\LineSkus\UpdateLineSkuRequest;
use App\Http\Resources\LineSkus\LineSkuResource;
use App\Http\Resources\LineSkus\PaginatedLineSkusResource;
use App\Interfaces\LineSkus\LineSkusServiceInterface;
use Illuminate\Http\Request;

class LineSkusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, LineSkusServiceInterface $service)
    {
        try {
            $limit = $request->query('limit');
            $response = $service->getLineSkus($limit);

            $data = $limit ? new PaginatedLineSkusResource($response) : LineSkuResource::collection($response);

            return ResponseHandler::success($data, 'Rendimientos Obtenidos Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateLineSkuRequest $request, LineSkusServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $response = $service->createLineSku($data);

            return ResponseHandler::success($response, 'Rendimiento Creado Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, LineSkusServiceInterface $service)
    {
        try {
            $response = $service->getLineSkuById($id);

            return ResponseHandler::success(new LineSkuResource($response), 'Rendimiento Obtenido Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateLineSkuRequest $request, string $id, LineSkusServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $response = $service->updateLineSkuById($id, $data);

            return ResponseHandler::success($response, 'Rendimiento Actualizado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, LineSkusServiceInterface $service)
    {
        try {
            $response = $service->deleteLineSkuById($id);

            return ResponseHandler::success($response, 'Rendimiento Eliminado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
