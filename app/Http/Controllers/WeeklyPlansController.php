<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Resources\WeeklyPlans\PaginatedWeeklyPlansResource;
use App\Http\Resources\WeeklyPlans\WeeklyPlanResource;
use App\Interfaces\WeeklyPlans\WeeklyPlansServiceInterface;
use Illuminate\Http\Request;

class WeeklyPlansController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, WeeklyPlansServiceInterface $service)
    {
        try {
            $limit = $request->query('limit');
            $data = $service->getWeeklyPlans($limit);

            $response = $limit ? new PaginatedWeeklyPlansResource($data) : WeeklyPlanResource::collection($data);

            return ResponseHandler::success($response, 'Planes Obtenidos Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, WeeklyPlansServiceInterface $service)
    {
        try {
            $data = $service->getWeeklyPlanById($id);

            return ResponseHandler::success(new WeeklyPlanResource($data), 'Plane Obtenido Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
