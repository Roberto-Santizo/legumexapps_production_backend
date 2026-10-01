<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\WeeklyPlanTaskEmployees\AddWeeklyPlanTaskEmployeeRequest;
use App\Http\Requests\WeeklyPlanTaskEmployees\ConfirmWeeklyPlanTaskEmployeesRequest;
use App\Http\Requests\WeeklyPlanTaskEmployees\ReplaceWeeklyPlanTaskEmployeeRequest;
use App\Http\Resources\WeeklyPlanEmployees\WeeklyPlanEmployeeResource;
use App\Http\Resources\WeeklyPlanTaskEmployees\WeeklyPlanTaskEmployeeResource;
use App\Interfaces\WeeklyPlanTaskEmployees\WeeklyPlanTaskEmployeesServiceInterface;

class WeeklyPlanTaskEmployeesController extends Controller
{
    public function availableEmployees(string $id, WeeklyPlanTaskEmployeesServiceInterface $service)
    {
        try {
            $candidates = $service->getAvailableEmployees($id);

            return ResponseHandler::success(WeeklyPlanEmployeeResource::collection($candidates), 'Candidatos Obtenidos Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    public function employees(string $id, WeeklyPlanTaskEmployeesServiceInterface $service)
    {
        try {
            $assignments = $service->getTaskEmployees($id);

            return ResponseHandler::success(WeeklyPlanTaskEmployeeResource::collection($assignments), 'Personal Asignado Obtenido Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    public function confirmEmployees(ConfirmWeeklyPlanTaskEmployeesRequest $request, string $id, WeeklyPlanTaskEmployeesServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $result = $service->confirmEmployees($id, $data);

            return ResponseHandler::success($result, 'Personal Asignado Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    public function addEmployee(AddWeeklyPlanTaskEmployeeRequest $request, string $id, WeeklyPlanTaskEmployeesServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $result = $service->addEmployee($id, $data['weekly_plan_employee_id']);

            return ResponseHandler::success($result, 'Empleado Agregado Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    public function replaceEmployee(ReplaceWeeklyPlanTaskEmployeeRequest $request, string $id, WeeklyPlanTaskEmployeesServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $result = $service->replaceEmployee($id, $data['weekly_plan_employee_id']);

            return ResponseHandler::success($result, 'Empleado Reemplazado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
