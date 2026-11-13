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
            'name' => $this->name,
            'code' => $this->code,
            'position_id' => $this->position_id,
            'position' => $this->position?->code,
            'activity' => $this->position?->activity,
        ];
    }
}
