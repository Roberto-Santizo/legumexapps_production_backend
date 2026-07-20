<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\Skus\CreateSkuRequest;
use App\Http\Requests\Skus\UpdateSkuRequest;
use App\Http\Resources\Skus\PaginatedSkusResource;
use App\Http\Resources\Skus\SkuResource;
use App\Interfaces\Skus\SkusServiceInterface;
use Illuminate\Http\Request;

class SkusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, SkusServiceInterface $service)
    {
        try {
            $limit = $request->query('limit');
            $response = $service->getSkus($limit);

            $data = $limit ? new PaginatedSkusResource($response) : SkuResource::collection($response);

            return ResponseHandler::success($data, 'SKUs Obtenidos Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateSkuRequest $request, SkusServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $response = $service->createSku($data);

            return ResponseHandler::success($response, 'SKU Creado Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, SkusServiceInterface $service)
    {
        try {
            $response = $service->getSkuByCode($id);

            return ResponseHandler::success(new SkuResource($response), 'SKU Obtenido Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSkuRequest $request, string $id, SkusServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $response = $service->updateSkuById($id, $data);

            return ResponseHandler::success($response, 'SKU Actualizado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, SkusServiceInterface $service)
    {
        try {
            $response = $service->deleteSkuById($id);

            return ResponseHandler::success($response, 'SKU Eliminado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
