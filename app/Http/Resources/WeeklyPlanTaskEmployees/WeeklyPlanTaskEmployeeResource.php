<?php

namespace App\Http\Resources\WeeklyPlanTaskEmployees;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeeklyPlanTaskEmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'weekly_plan_employee_id' => $this->weekly_plan_employee_id,
            'name' => $this->weeklyPlanEmployee->employee->name,
            'code' => $this->weeklyPlanEmployee->employee->code,
            'position_id' => $this->position_id,
            'position' => $this->position->code,
            'replaced_weekly_plan_employee_id' => $this->replaced_weekly_plan_employee_id,
            'replaced_name' => $this->replacedWeeklyPlanEmployee?->employee->name,
            'replaced_code' => $this->replacedWeeklyPlanEmployee?->employee->code,
        ];
    }
}
