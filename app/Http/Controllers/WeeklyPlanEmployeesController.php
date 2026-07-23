<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\Shared\UploadFileRequest;
use App\Http\Requests\WeeklyPlanEmployees\CreateWeeklyPlanEmployeeRequest;
use App\Http\Requests\WeeklyPlanEmployees\UpdateWeeklyPlanEmployeeRequest;
use App\Http\Resources\WeeklyPlanEmployees\PaginatedWeeklyPlanEmployeesResource;
use App\Http\Resources\WeeklyPlanEmployees\WeeklyPlanEmployeeResource;
use App\Interfaces\WeeklyPlanEmployees\WeeklyPlanEmployeesServiceInterface;
use Illuminate\Http\Request;

class WeeklyPlanEmployeesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, WeeklyPlanEmployeesServiceInterface $service)
    {
        try {
            $limit = $request->query('limit');
            $weeklyPlanEmployees = $service->getWeeklyPlanEmployees($limit);

            $response = $limit ? new PaginatedWeeklyPlanEmployeesResource($weeklyPlanEmployees) : WeeklyPlanEmployeeResource::collection($weeklyPlanEmployees);

            return ResponseHandler::success($response, 'Empleados del Plan Semanal Obtenidos Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateWeeklyPlanEmployeeRequest $request, WeeklyPlanEmployeesServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $result = $service->createWeeklyPlanEmployee($data);

            return ResponseHandler::success($result, 'Empleado del Plan Semanal Creado Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, WeeklyPlanEmployeesServiceInterface $service)
    {
        try {
            $weeklyPlanEmployee = $service->getWeeklyPlanEmployeeById($id);

            return ResponseHandler::success(new WeeklyPlanEmployeeResource($weeklyPlanEmployee), 'Empleado del Plan Semanal Obtenido Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWeeklyPlanEmployeeRequest $request, string $id, WeeklyPlanEmployeesServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $result = $service->updateWeeklyPlanEmployeeById($data, $id);

            return ResponseHandler::success($result, 'Empleado del Plan Semanal Actualizado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, WeeklyPlanEmployeesServiceInterface $service)
    {
        try {
            $result = $service->deleteWeeklyPlanEmployeeById($id);

            return ResponseHandler::success($result, 'Empleado del Plan Semanal Eliminado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    public function uploadFile(UploadFileRequest $request, WeeklyPlanEmployeesServiceInterface $service)
    {
        try {
            $file = $request->validated();
            $result = $service->uploadFile($file['file']);

            return ResponseHandler::success($result, 'Archivo Subido Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
