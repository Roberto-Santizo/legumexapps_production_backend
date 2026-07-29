<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\DraftWeeklyPlanTasks\CreateDraftWeeklyPlanTaskRequest;
use App\Http\Requests\DraftWeeklyPlanTasks\UpdateDraftWeeklyPlanTaskRequest;
use App\Http\Resources\DraftWeeklyPlanTasks\DraftWeeklyPlanTaskResource;
use App\Http\Resources\DraftWeeklyPlanTasks\PaginatedDraftWeeklyPlanTasksResource;
use App\Interfaces\DraftWeeklyPlanTasks\DraftWeeklyPlanTasksServiceInterface;
use Illuminate\Http\Request;

class DraftWeeklyPlanTasksController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, DraftWeeklyPlanTasksServiceInterface $service)
    {
        try {
            $limit = $request->query('limit');
            $draftWeeklyPlanTasks = $service->getDraftWeeklyPlanTasks($limit, $request);

            $response = $limit ? new PaginatedDraftWeeklyPlanTasksResource($draftWeeklyPlanTasks) : DraftWeeklyPlanTaskResource::collection($draftWeeklyPlanTasks);

            return ResponseHandler::success($response, 'Tareas del Plan Borrador Obtenidas Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateDraftWeeklyPlanTaskRequest $request, DraftWeeklyPlanTasksServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $result = $service->createDraftWeeklyPlanTask($data);

            return ResponseHandler::success($result, 'Tarea del Plan Borrador Creada Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, DraftWeeklyPlanTasksServiceInterface $service)
    {
        try {
            $draftWeeklyPlanTask = $service->getDraftWeeklyPlanTaskById($id);

            return ResponseHandler::success(new DraftWeeklyPlanTaskResource($draftWeeklyPlanTask), 'Tarea del Plan Borrador Obtenida Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDraftWeeklyPlanTaskRequest $request, string $id, DraftWeeklyPlanTasksServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $result = $service->updateDraftWeeklyPlanTaskById($id, $data);

            return ResponseHandler::success($result, 'Tarea del Plan Borrador Actualizada Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, DraftWeeklyPlanTasksServiceInterface $service)
    {
        try {
            $result = $service->deleteDraftWeeklyPlanTaskById($id);

            return ResponseHandler::success($result, 'Tarea del Plan Borrador Eliminada Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
