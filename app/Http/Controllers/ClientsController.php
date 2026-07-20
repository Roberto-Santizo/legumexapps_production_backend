<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\Clients\CreateClientRequest;
use App\Http\Requests\Clients\UpdateClientRequest;
use App\Http\Resources\Clients\ClientResource;
use App\Http\Resources\Clients\PaginatedClientsResource;
use App\Interfaces\Clients\ClientsServiceInterface;
use Illuminate\Http\Request;

class ClientsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, ClientsServiceInterface $service)
    {
        try {
            $limit = $request->query('limit');
            $response = $service->getClients($limit);

            $data = $limit ? new PaginatedClientsResource($response) : ClientResource::collection($response);
            
            return ResponseHandler::success($data, 'Clientes Obtenidos Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateClientRequest $request, ClientsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $response = $service->createClient($data);

            return ResponseHandler::success($response, 'Cliente Creado Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, ClientsServiceInterface $service)
    {
        try {
            $response = $service->getClientById($id);

            return ResponseHandler::success(new ClientResource($response), 'Cliente Obtenido Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateClientRequest $request, string $id, ClientsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $response = $service->updateClientById($id, $data);

            return ResponseHandler::success($response, 'Cliente Actualizado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, ClientsServiceInterface $service)
    {
        try {
            $response = $service->deleteClientById($id);

            return ResponseHandler::success($response, 'Cliente Eliminado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
