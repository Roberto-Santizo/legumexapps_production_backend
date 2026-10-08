<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\CaptureFields\CreateCaptureFieldRequest;
use App\Http\Requests\CaptureFields\UpdateCaptureFieldRequest;
use App\Http\Resources\CaptureFields\CaptureFieldResource;
use App\Http\Resources\CaptureFields\PaginatedCaptureFieldsResource;
use App\Interfaces\CaptureFields\CaptureFieldsServiceInterface;
use Illuminate\Http\Request;

class CaptureFieldsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, CaptureFieldsServiceInterface $service)
    {
        try {
            $limit = $request->query('limit');
            $response = $service->getCaptureFields($limit, $request);

            $data = $limit ? new PaginatedCaptureFieldsResource($response) : CaptureFieldResource::collection($response);

            return ResponseHandler::success($data, 'Campos Obtenidos Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateCaptureFieldRequest $request, CaptureFieldsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $response = $service->createCaptureField($data);

            return ResponseHandler::success(new CaptureFieldResource($response), 'Campo Creado Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, CaptureFieldsServiceInterface $service)
    {
        try {
            $response = $service->getCaptureFieldById($id);

            return ResponseHandler::success(new CaptureFieldResource($response), 'Campo Obtenido Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCaptureFieldRequest $request, string $id, CaptureFieldsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $service->updateCaptureFieldById($id, $data);

            return ResponseHandler::success(null, 'Campo Actualizado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, CaptureFieldsServiceInterface $service)
    {
        try {
            $service->deleteCaptureFieldById($id);

            return ResponseHandler::success(null, 'Campo Eliminado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
