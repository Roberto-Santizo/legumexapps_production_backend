<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\SkuRawMaterials\CreateSkuRawMaterialRequest;
use App\Http\Requests\SkuRawMaterials\UpdateSkuRawMaterialRequest;
use App\Http\Resources\SkuRawMaterials\SkuRawMaterialResource;
use App\Interfaces\SkuRawMaterials\SkuRawMaterialsServiceInterface;
use Illuminate\Http\Request;

class SkuRawMaterialsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, SkuRawMaterialsServiceInterface $service)
    {
        try {
            $skuId = $request->query('skuId');
            $response = $service->getSkuRawMaterials($skuId);

            return ResponseHandler::success(SkuRawMaterialResource::collection($response), 'Materiales Crudos de SKU Obtenidos Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateSkuRawMaterialRequest $request, SkuRawMaterialsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $response = $service->createSkuRawMaterial($data);

            return ResponseHandler::success($response, 'Material Crudo de SKU Creado Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, SkuRawMaterialsServiceInterface $service)
    {
        try {
            $response = $service->getSkuRawMaterialById($id);

            return ResponseHandler::success(new SkuRawMaterialResource($response), 'Material Crudo de SKU Obtenido Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSkuRawMaterialRequest $request, string $id, SkuRawMaterialsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $response = $service->updateSkuRawMaterialById($id, $data);

            return ResponseHandler::success($response, 'Material Crudo de SKU Actualizado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, SkuRawMaterialsServiceInterface $service)
    {
        try {
            $response = $service->deleteSkuRawMaterialById($id);

            return ResponseHandler::success($response, 'Material Crudo de SKU Eliminado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
