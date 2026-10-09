<?php

namespace App\Http\Resources\WeeklyPlanTaskLotRecords;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeeklyPlanTaskLotRecordResource extends JsonResource
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
            'entry_date' => $this->entry_date?->format('Y-m-d'),
            'lot' => $this->lot,
            'recorded_at' => $this->recorded_at ? substr($this->recorded_at, 0, 5) : null,
            'intake_lbs' => $this->roundOrNull($this->intake_lbs),
            'applied_raw_lbs' => $this->roundOrNull($this->applied_raw_lbs),
            'trimmed_lbs' => $this->roundOrNull($this->trimmed_lbs),
            'overripe_lbs' => $this->roundOrNull($this->overripe_lbs),
            'recovery_pct' => $this->roundOrNull($this->recovery_pct),
            'overripe_pct' => $this->roundOrNull($this->overripe_pct),
            'grn_balance' => $this->roundOrNull($this->grn_balance),
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
