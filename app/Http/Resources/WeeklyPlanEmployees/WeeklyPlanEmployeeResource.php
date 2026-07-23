<?php

namespace App\Http\Resources\WeeklyPlanEmployees;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeeklyPlanEmployeeResource extends JsonResource
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
            'name' => $this->employee->name,
            'code' => $this->employee->code,
            'position' => $this->position->code,
            'biometric_position' => $this->employee->position,
            'position_id' => $this->position_id,
            'employee_id' => $this->employee_id,
        ];
    }
}
