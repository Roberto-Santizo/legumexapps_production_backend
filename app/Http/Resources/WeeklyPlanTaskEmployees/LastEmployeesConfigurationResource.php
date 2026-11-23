<?php

namespace App\Http\Resources\WeeklyPlanTaskEmployees;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LastEmployeesConfigurationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'employees' => ConfirmWeeklyPlanTaskEmployeeResource::collection($this->resource),
        ];
    }
}
