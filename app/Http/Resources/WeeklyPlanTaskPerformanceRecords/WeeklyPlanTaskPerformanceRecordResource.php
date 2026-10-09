<?php

namespace App\Http\Resources\WeeklyPlanTaskPerformanceRecords;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeeklyPlanTaskPerformanceRecordResource extends JsonResource
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
            'pallet_number' => $this->pallet_number,
            'boxes' => $this->boxes,
            'net_weight' => round($this->net_weight, 2),
            'ticket_weight' => round($this->ticket_weight, 2),
            'difference' => round($this->difference, 2),
            'user_id' => $this->user_id,
            'user_name' => $this->user->name,
            'created_at' => $this->created_at->format('d-m-Y H:i'),
            'updated_at' => $this->updated_at->format('d-m-Y H:i'),
        ];
    }
}
