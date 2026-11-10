<?php

namespace App\Http\Resources\WeeklyPlanTaskProductRecords;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeeklyPlanTaskProductRecordResource extends JsonResource
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
            'raw_material' => $this->raw_material,
            'raw_lbs' => $this->roundOrNull($this->raw_lbs),
            'classified_lbs' => $this->roundOrNull($this->classified_lbs),
            'trimmed_lbs' => $this->roundOrNull($this->trimmed_lbs),
            'rejected_lbs' => $this->roundOrNull($this->rejected_lbs),
            'packed_lbs' => $this->roundOrNull($this->packed_lbs),
            'recovery_pct' => $this->roundOrNull($this->recovery_pct),
            'rejection_pct' => $this->roundOrNull($this->rejection_pct),
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
