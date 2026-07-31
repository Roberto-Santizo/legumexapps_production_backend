<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\PackingMaterialTransactionItems\CreatePackingMaterialTransactionItemRequest;
use App\Http\Requests\PackingMaterialTransactionItems\UpdatePackingMaterialTransactionItemRequest;
use App\Http\Resources\PackingMaterialTransactionItems\PackingMaterialTransactionItemResource;
use App\Http\Resources\PackingMaterialTransactionItems\PaginatedPackingMaterialTransactionItemsResource;
use App\Interfaces\PackingMaterialTransactionItems\PackingMaterialTransactionItemsServiceInterface;
use Illuminate\Http\Request;

class PackingMaterialTransactionItemsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, PackingMaterialTransactionItemsServiceInterface $service)
    {
        try {
            $limit = $request->query('limit');
            $response = $service->getPackingMaterialTransactionItems($limit, $request);

            $data = $limit ? new PaginatedPackingMaterialTransactionItemsResource($response) : PackingMaterialTransactionItemResource::collection($response);

            return ResponseHandler::success($data, 'Detalles de Transacción de Material de Empaque Obtenidos Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreatePackingMaterialTransactionItemRequest $request, PackingMaterialTransactionItemsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $response = $service->createPackingMaterialTransactionItem($data);

            return ResponseHandler::success($response, 'Detalle de Transacción de Material de Empaque Creado Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, PackingMaterialTransactionItemsServiceInterface $service)
    {
        try {
            $response = $service->getPackingMaterialTransactionItemById($id);

            return ResponseHandler::success(new PackingMaterialTransactionItemResource($response), 'Detalle de Transacción de Material de Empaque Obtenido Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePackingMaterialTransactionItemRequest $request, string $id, PackingMaterialTransactionItemsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $response = $service->updatePackingMaterialTransactionItemById($id, $data);

            return ResponseHandler::success($response, 'Detalle de Transacción de Material de Empaque Actualizado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, PackingMaterialTransactionItemsServiceInterface $service)
    {
        try {
            $response = $service->deletePackingMaterialTransactionItemById($id);

            return ResponseHandler::success($response, 'Detalle de Transacción de Material de Empaque Eliminado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
