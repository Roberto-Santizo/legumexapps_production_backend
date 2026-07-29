<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\DraftWeeklyPlans\CreateDraftWeeklyPlanRequest;
use App\Http\Requests\DraftWeeklyPlans\UpdateDraftWeeklyPlanRequest;
use App\Http\Resources\DraftWeeklyPlans\DraftWeeklyPlanResource;
use App\Http\Resources\DraftWeeklyPlans\PaginatedDraftWeeklyPlansResource;
use App\Interfaces\DraftWeeklyPlans\DraftWeeklyPlansServiceInterface;
use Illuminate\Http\Request;

class DraftWeeklyPlansController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, DraftWeeklyPlansServiceInterface $service)
    {
        try {
            $limit = $request->query('limit');
            $response = $service->getDraftWeeklyPlans($limit);

            $data = $limit ? new PaginatedDraftWeeklyPlansResource($response) : DraftWeeklyPlanResource::collection($response);

            return ResponseHandler::success($data, 'Planes Borrador Obtenidos Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateDraftWeeklyPlanRequest $request, DraftWeeklyPlansServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $response = $service->createDraftWeeklyPlan($data);

            return ResponseHandler::success($response, 'Plan Borrador Creado Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, DraftWeeklyPlansServiceInterface $service)
    {
        try {
            $response = $service->getDraftWeeklyPlanById($id);

            return ResponseHandler::success(new DraftWeeklyPlanResource($response), 'Plan Borrador Obtenido Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDraftWeeklyPlanRequest $request, string $id, DraftWeeklyPlansServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $response = $service->updateDraftWeeklyPlanById($id, $data);

            return ResponseHandler::success($response, 'Plan Borrador Actualizado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, DraftWeeklyPlansServiceInterface $service)
    {
        try {
            $response = $service->deleteDraftWeeklyPlanById($id);

            return ResponseHandler::success($response, 'Plan Borrador Eliminado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Confirm the specified draft weekly plan.
     */
    public function confirm(string $id, DraftWeeklyPlansServiceInterface $service)
    {
        try {
            $response = $service->confirmDraftWeeklyPlanById($id);

            return ResponseHandler::success($response, 'Plan Borrador Confirmado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
