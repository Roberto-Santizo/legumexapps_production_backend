<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\WeeklyPlanTaskProductRecords\CreateWeeklyPlanTaskProductRecordRequest;
use App\Http\Requests\WeeklyPlanTaskProductRecords\UpdateWeeklyPlanTaskProductRecordRequest;
use App\Http\Resources\WeeklyPlanTaskProductRecords\WeeklyPlanTaskProductRecordResource;
use App\Interfaces\WeeklyPlanTaskProductRecords\WeeklyPlanTaskProductRecordsServiceInterface;
use Illuminate\Http\Request;

class WeeklyPlanTaskProductRecordsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, WeeklyPlanTaskProductRecordsServiceInterface $service)
    {
        try {
            $records = $service->getWeeklyPlanTaskProductRecords($request);

            return ResponseHandler::success(WeeklyPlanTaskProductRecordResource::collection($records), 'Registros de Producto Obtenidos Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateWeeklyPlanTaskProductRecordRequest $request, WeeklyPlanTaskProductRecordsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $record = $service->createWeeklyPlanTaskProductRecord($data);

            return ResponseHandler::success(new WeeklyPlanTaskProductRecordResource($record->load('user')), 'Registro de Producto Creado Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, WeeklyPlanTaskProductRecordsServiceInterface $service)
    {
        try {
            $record = $service->getWeeklyPlanTaskProductRecordById($id);

            return ResponseHandler::success(new WeeklyPlanTaskProductRecordResource($record), 'Registro de Producto Obtenido Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWeeklyPlanTaskProductRecordRequest $request, string $id, WeeklyPlanTaskProductRecordsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $service->updateWeeklyPlanTaskProductRecordById($data, $id);

            return ResponseHandler::success(null, 'Registro de Producto Actualizado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, WeeklyPlanTaskProductRecordsServiceInterface $service)
    {
        try {
            $service->deleteWeeklyPlanTaskProductRecordById($id);

            return ResponseHandler::success(null, 'Registro de Producto Eliminado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
