<?php

namespace App\Http\Resources\WeeklyPlanTaskEmployees;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConfirmWeeklyPlanTaskEmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'position' => $this->position ?? $this->catalogPosition?->code,
            'line_sku_activity_id' => $this->line_sku_activity_id,
        ];
    }
}
