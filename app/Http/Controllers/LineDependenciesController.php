<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\LineDependencies\CreateLineDependencyRequest;
use App\Http\Resources\LineDependencies\LineDependencyResource;
use App\Interfaces\LineDependencies\LineDependenciesServiceInterface;
use Illuminate\Http\Request;

class LineDependenciesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, LineDependenciesServiceInterface $service)
    {
        try {
            $lineId = $request->query('lineId');
            $data = $service->get($lineId);

            return ResponseHandler::success(LineDependencyResource::collection($data), 'Dependencias Obtenidas Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateLineDependencyRequest $request, LineDependenciesServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $response = $service->create($data);

            return ResponseHandler::success($response, 'Dependencia Creada Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, LineDependenciesServiceInterface $service)
    {
        try {
            $response = $service->delete($id);

            return ResponseHandler::success($response, 'Dependencia Eliminada Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
