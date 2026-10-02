<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\WeeklyPlanTaskPerformanceRecords\CreateWeeklyPlanTaskPerformanceRecordRequest;
use App\Http\Requests\WeeklyPlanTaskPerformanceRecords\UpdateWeeklyPlanTaskPerformanceRecordRequest;
use App\Http\Resources\WeeklyPlanTaskPerformanceRecords\WeeklyPlanTaskPerformanceRecordResource;
use App\Interfaces\WeeklyPlanTaskPerformanceRecords\WeeklyPlanTaskPerformanceRecordsServiceInterface;
use Illuminate\Http\Request;

class WeeklyPlanTaskPerformanceRecordsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, WeeklyPlanTaskPerformanceRecordsServiceInterface $service)
    {
        try {
            $records = $service->getWeeklyPlanTaskPerformanceRecords($request);

            return ResponseHandler::success(WeeklyPlanTaskPerformanceRecordResource::collection($records), 'Tomas de Rendimiento Obtenidas Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateWeeklyPlanTaskPerformanceRecordRequest $request, WeeklyPlanTaskPerformanceRecordsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $record = $service->createWeeklyPlanTaskPerformanceRecord($data);

            return ResponseHandler::success(new WeeklyPlanTaskPerformanceRecordResource($record->load('user')), 'Toma de Rendimiento Creada Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, WeeklyPlanTaskPerformanceRecordsServiceInterface $service)
    {
        try {
            $record = $service->getWeeklyPlanTaskPerformanceRecordById($id);

            return ResponseHandler::success(new WeeklyPlanTaskPerformanceRecordResource($record), 'Toma de Rendimiento Obtenida Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWeeklyPlanTaskPerformanceRecordRequest $request, string $id, WeeklyPlanTaskPerformanceRecordsServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $result = $service->updateWeeklyPlanTaskPerformanceRecordById($data, $id);

            return ResponseHandler::success($result, 'Toma de Rendimiento Actualizada Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, WeeklyPlanTaskPerformanceRecordsServiceInterface $service)
    {
        try {
            $result = $service->deleteWeeklyPlanTaskPerformanceRecordById($id);

            return ResponseHandler::success($result, 'Toma de Rendimiento Eliminada Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
