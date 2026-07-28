<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\RawMaterials\CreateRawMaterialRequest;
use App\Http\Requests\RawMaterials\UpdateRawMaterialRequest;
use App\Http\Resources\RawMaterials\PaginatedRawMaterialsResource;
use App\Http\Resources\RawMaterials\RawMaterialResource;
use App\Interfaces\RawMaterials\RawMaterialsServiceInterface;
use Illuminate\Http\Request;

class RawMaterialsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, RawMaterialsServiceInterface $service)
    {
        try {
            $limit = $request->query('limit');
            $response = $service->getRawMaterials($limit);

            $data = $limit ? new PaginatedRawMaterialsResource($response) : RawMaterialResource::collection($response);

            return ResponseHandler::success($data, 'Materias Primas Obtenidas Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateRawMaterialRequest $request, RawMaterialsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $response = $service->createRawMaterial($data);

            return ResponseHandler::success($response, 'Materia Prima Creada Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, RawMaterialsServiceInterface $service)
    {
        try {
            $response = $service->getRawMaterialByCode($id);

            return ResponseHandler::success(new RawMaterialResource($response), 'Materia Prima Obtenida Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRawMaterialRequest $request, string $id, RawMaterialsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $response = $service->updateRawMaterialById($id, $data);

            return ResponseHandler::success($response, 'Materia Prima Actualizada Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, RawMaterialsServiceInterface $service)
    {
        try {
            $response = $service->deleteRawMaterialById($id);

            return ResponseHandler::success($response, 'Materia Prima Eliminada Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
