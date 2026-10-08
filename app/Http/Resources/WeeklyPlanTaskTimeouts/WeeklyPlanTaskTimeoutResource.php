<?php

namespace App\Http\Resources\WeeklyPlanTaskTimeouts;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeeklyPlanTaskTimeoutResource extends JsonResource
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
            'weekly_plan_task_id' => $this->weekly_plan_task_id,
            'timeout_id' => $this->timeout_id,
            'timeout_name' => $this->timeout->name,
            'user_id' => $this->user_id,
            'user_name' => $this->user->name,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'duration_hours' => $this->duration_hours !== null ? round($this->duration_hours, 2) : null,
            'observation' => $this->observation,
            'is_open' => $this->end_date === null,
        ];
    }
}
