<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\LineFields\CreateLineFieldRequest;
use App\Http\Requests\LineFields\UpdateLineFieldRequest;
use App\Http\Resources\LineFields\LineFieldResource;
use App\Interfaces\LineFields\LineFieldsServiceInterface;

class LineFieldsController extends Controller
{
    public function fields(string $code, LineFieldsServiceInterface $service)
    {
        try {
            $lineFields = $service->getLineFields($code);

            return ResponseHandler::success(LineFieldResource::collection($lineFields), 'Campos de la Línea Obtenidos Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    public function assignField(CreateLineFieldRequest $request, string $code, LineFieldsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $lineField = $service->createLineField($code, $data);

            return ResponseHandler::success(new LineFieldResource($lineField->load('captureField')), 'Campo Asignado Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    public function updateField(UpdateLineFieldRequest $request, string $id, LineFieldsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $service->updateLineFieldById($id, $data);

            return ResponseHandler::success(null, 'Campo de la Línea Actualizado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    public function removeField(string $id, LineFieldsServiceInterface $service)
    {
        try {
            $service->deleteLineFieldById($id);

            return ResponseHandler::success(null, 'Campo Quitado de la Línea Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
