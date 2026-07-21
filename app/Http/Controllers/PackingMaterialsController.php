<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\PackingMaterials\CreatePackingMaterialRequest;
use App\Http\Requests\PackingMaterials\UpdatePackingMaterialRequest;
use App\Http\Resources\PackingMaterials\PackingMaterialResource;
use App\Http\Resources\PackingMaterials\PaginatedPackingMaterialsResource;
use App\Interfaces\PackingMaterials\PackingMaterialsServiceInterface;
use Illuminate\Http\Request;

class PackingMaterialsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, PackingMaterialsServiceInterface $service)
    {
        try {
            $limit = $request->query('limit');
            $response = $service->getPackingMaterials($limit);

            $data = $limit ? new PaginatedPackingMaterialsResource($response) : PackingMaterialResource::collection($response);

            return ResponseHandler::success($data, 'Materiales de Empaque Obtenidos Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreatePackingMaterialRequest $request, PackingMaterialsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $response = $service->createPackingMaterial($data);

            return ResponseHandler::success($response, 'Material de Empaque Creado Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, PackingMaterialsServiceInterface $service)
    {
        try {
            $response = $service->getPackingMaterialByCode($id);

            return ResponseHandler::success($response, 'Material de Empaque Obtenido Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePackingMaterialRequest $request, string $id, PackingMaterialsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $response = $service->updatePackingMaterialById($id, $data);

            return ResponseHandler::success($response, 'Material de Empaque Actualizado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, PackingMaterialsServiceInterface $service)
    {
        try {
            $response = $service->deletePackingMaterialById($id);

            return ResponseHandler::success($response, 'Material de Empaque Eliminado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
