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
            'position' => $this->catalogPosition?->code,
            'position_activity' => $this->catalogPosition?->activity?->name,
            'line_sku_activity_id' => $this->line_sku_activity_id,
            'activity' => $this->lineSkuActivity?->activity?->name,
            'unit' => $this->lineSkuActivity?->activity?->unit,
        ];
    }
}
