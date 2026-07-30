<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\WeeklyPlanTasks\AssignOperationDateRequest;
use App\Http\Requests\WeeklyPlanTasks\CreateWeeklyPlanTaskRequest;
use App\Http\Requests\WeeklyPlanTasks\SplitWeeklyPlanTaskRequest;
use App\Http\Requests\WeeklyPlanTasks\UpdateWeeklyPlanTaskRequest;
use App\Http\Resources\WeeklyPlanTasks\PaginatedWeeklyPlanTasksResource;
use App\Http\Resources\WeeklyPlanTasks\WeeklyPlanTaskResource;
use App\Interfaces\WeeklyPlanTasks\WeeklyPlanTasksServiceInterface;
use Illuminate\Http\Request;

class WeeklyPlanTasksController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, WeeklyPlanTasksServiceInterface $service)
    {
        try {
            $limit = $request->query('limit');
            $weeklyPlanTasks = $service->getWeeklyPlanTasks($limit, $request);

            $response = $limit ? new PaginatedWeeklyPlanTasksResource($weeklyPlanTasks) : WeeklyPlanTaskResource::collection($weeklyPlanTasks);

            return ResponseHandler::success($response, 'Tareas del Plan Semanal Obtenidas Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateWeeklyPlanTaskRequest $request, WeeklyPlanTasksServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $result = $service->createWeeklyPlanTask($data);

            return ResponseHandler::success($result, 'Tarea del Plan Semanal Creada Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, WeeklyPlanTasksServiceInterface $service)
    {
        try {
            $weeklyPlanTask = $service->getWeeklyPlanTaskById($id);

            return ResponseHandler::success(new WeeklyPlanTaskResource($weeklyPlanTask), 'Tarea del Plan Semanal Obtenida Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWeeklyPlanTaskRequest $request, string $id, WeeklyPlanTasksServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $result = $service->updateWeeklyPlanTaskById($data, $id);

            return ResponseHandler::success($result, 'Tarea del Plan Semanal Actualizada Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, WeeklyPlanTasksServiceInterface $service)
    {
        try {
            $result = $service->deleteWeeklyPlanTaskById($id);

            return ResponseHandler::success($result, 'Tarea del Plan Semanal Eliminada Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Assign the operation date to the given weekly plan tasks.
     */
    public function assignOperationDate(AssignOperationDateRequest $request, WeeklyPlanTasksServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $result = $service->assignOperationDate($data['tasksIds'], $data['operation_date']);

            return ResponseHandler::success($result, 'Fecha de Operación Asignada Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Split a weekly plan task into several tasks across different dates, then delete the original task.
     */
    public function splitTask(SplitWeeklyPlanTaskRequest $request, WeeklyPlanTasksServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $result = $service->splitWeeklyPlanTask($data['task_id'], $data['portions']);

            return ResponseHandler::success(WeeklyPlanTaskResource::collection($result), 'Tarea Dividida Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
