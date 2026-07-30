<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\PackingMaterialTransactions\CreatePackingMaterialTransactionRequest;
use App\Http\Requests\PackingMaterialTransactions\UpdatePackingMaterialTransactionRequest;
use App\Http\Resources\PackingMaterialTransactions\PackingMaterialTransactionResource;
use App\Http\Resources\PackingMaterialTransactions\PaginatedPackingMaterialTransactionsResource;
use App\Interfaces\PackingMaterialTransactions\PackingMaterialTransactionsServiceInterface;
use Illuminate\Http\Request;

class PackingMaterialTransactionsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, PackingMaterialTransactionsServiceInterface $service)
    {
        try {
            $limit = $request->query('limit');
            $response = $service->getPackingMaterialTransactions($limit);

            $data = $limit ? new PaginatedPackingMaterialTransactionsResource($response) : PackingMaterialTransactionResource::collection($response);

            return ResponseHandler::success($data, 'Transacciones de Material de Empaque Obtenidas Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreatePackingMaterialTransactionRequest $request, PackingMaterialTransactionsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $response = $service->createPackingMaterialTransaction($data);

            return ResponseHandler::success($response, 'Transacción de Material de Empaque Creada Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, PackingMaterialTransactionsServiceInterface $service)
    {
        try {
            $response = $service->getPackingMaterialTransactionById($id);

            return ResponseHandler::success(new PackingMaterialTransactionResource($response), 'Transacción de Material de Empaque Obtenida Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePackingMaterialTransactionRequest $request, string $id, PackingMaterialTransactionsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $response = $service->updatePackingMaterialTransactionById($data, $id);

            return ResponseHandler::success($response, 'Transacción de Material de Empaque Actualizada Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, PackingMaterialTransactionsServiceInterface $service)
    {
        try {
            $response = $service->deletePackingMaterialTransactionById($id);

            return ResponseHandler::success($response, 'Transacción de Material de Empaque Eliminada Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
