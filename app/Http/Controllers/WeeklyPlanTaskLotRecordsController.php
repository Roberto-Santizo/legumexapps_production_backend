<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\WeeklyPlanTaskLotRecords\CreateWeeklyPlanTaskLotRecordRequest;
use App\Http\Requests\WeeklyPlanTaskLotRecords\UpdateWeeklyPlanTaskLotRecordRequest;
use App\Http\Resources\WeeklyPlanTaskLotRecords\WeeklyPlanTaskLotRecordResource;
use App\Interfaces\WeeklyPlanTaskLotRecords\WeeklyPlanTaskLotRecordsServiceInterface;
use Illuminate\Http\Request;

class WeeklyPlanTaskLotRecordsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, WeeklyPlanTaskLotRecordsServiceInterface $service)
    {
        try {
            $records = $service->getWeeklyPlanTaskLotRecords($request);

            return ResponseHandler::success(WeeklyPlanTaskLotRecordResource::collection($records), 'Registros de Lote Obtenidos Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateWeeklyPlanTaskLotRecordRequest $request, WeeklyPlanTaskLotRecordsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $record = $service->createWeeklyPlanTaskLotRecord($data);

            return ResponseHandler::success(new WeeklyPlanTaskLotRecordResource($record->load('user')), 'Registro de Lote Creado Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, WeeklyPlanTaskLotRecordsServiceInterface $service)
    {
        try {
            $record = $service->getWeeklyPlanTaskLotRecordById($id);

            return ResponseHandler::success(new WeeklyPlanTaskLotRecordResource($record), 'Registro de Lote Obtenido Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWeeklyPlanTaskLotRecordRequest $request, string $id, WeeklyPlanTaskLotRecordsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $service->updateWeeklyPlanTaskLotRecordById($data, $id);

            return ResponseHandler::success(null, 'Registro de Lote Actualizado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, WeeklyPlanTaskLotRecordsServiceInterface $service)
    {
        try {
            $service->deleteWeeklyPlanTaskLotRecordById($id);

            return ResponseHandler::success(null, 'Registro de Lote Eliminado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
