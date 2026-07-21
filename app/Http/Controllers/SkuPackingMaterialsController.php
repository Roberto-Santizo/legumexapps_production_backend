<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\SkuPackingMaterials\CreateSkuPackingMaterialRequest;
use App\Http\Requests\SkuPackingMaterials\UpdateSkuPackingMaterialRequest;
use App\Http\Resources\SkuPackingMaterials\PaginatedSkuPackingMaterialsResource;
use App\Http\Resources\SkuPackingMaterials\SkuPackingMaterialResource;
use App\Interfaces\SkuPackingMaterials\SkuPackingMaterialsServiceInterface;
use Illuminate\Http\Request;

class SkuPackingMaterialsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, SkuPackingMaterialsServiceInterface $service)
    {
        try {
            $skuId = $request->query('skuId');
            $response = $service->getSkuPackingMaterials($skuId);

            return ResponseHandler::success(SkuPackingMaterialResource::collection($response), 'Materiales de Empaque de SKU Obtenidos Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateSkuPackingMaterialRequest $request, SkuPackingMaterialsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $response = $service->createSkuPackingMaterial($data);

            return ResponseHandler::success($response, 'Material de Empaque de SKU Creado Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, SkuPackingMaterialsServiceInterface $service)
    {
        try {
            $response = $service->getSkuPackingMaterialById($id);

            return ResponseHandler::success(new SkuPackingMaterialResource($response), 'Material de Empaque de SKU Obtenido Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSkuPackingMaterialRequest $request, string $id, SkuPackingMaterialsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $response = $service->updateSkuPackingMaterialById($id, $data);

            return ResponseHandler::success($response, 'Material de Empaque de SKU Actualizado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, SkuPackingMaterialsServiceInterface $service)
    {
        try {
            $response = $service->deleteSkuPackingMaterialById($id);

            return ResponseHandler::success($response, 'Material de Empaque de SKU Eliminado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
