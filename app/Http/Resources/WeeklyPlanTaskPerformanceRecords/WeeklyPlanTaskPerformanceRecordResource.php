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
            'lot' => $this->lot,
            'recorded_at' => $this->recorded_at ? substr($this->recorded_at, 0, 5) : null,
            'boxes' => $this->boxes,
            'liters' => $this->liters,
            'status' => $this->status,
            'scale_weight' => $this->roundOrNull($this->scale_weight),
            'tare' => $this->roundOrNull($this->tare),
            'net_weight' => $this->roundOrNull($this->net_weight),
            'ticket_weight' => $this->roundOrNull($this->ticket_weight),
            'difference' => $this->roundOrNull($this->difference),
            'observations' => $this->observations,
            'extra_values' => (object) ($this->extra_values ?? []),
            'user_id' => $this->user_id,
            'user_name' => $this->user->name,
            'created_at' => $this->created_at->format('d-m-Y H:i'),
            'updated_at' => $this->updated_at->format('d-m-Y H:i'),
        ];
    }

    private function roundOrNull(?float $value): ?float
    {
        return $value !== null ? round($value, 2) : null;
    }
}
