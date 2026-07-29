<?php

namespace App\Http\Resources\DraftWeeklyPlanTasks;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DraftWeeklyPlanTaskResource extends JsonResource
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
            'boxes' => $this->boxes,
            'destination' => $this->destination,
            'hours' => $this->hours,
            'operation_date' => $this->operation_date,
            'operation_date_string' => $this->operation_date ? $this->operation_date->format('d-m-Y') : 'SIN PROGRAMACIÓN',
            'draft_weekly_plan_id' => $this->draft_weekly_plan_id,
            'sku_id' => $this->sku_id,
            'line_id' => $this->line_id,
            'sku_name' => $this->sku->product_name,
            'sku_code' => $this->sku->code,
            'line_name' => $this->line?->name,
            'line_code' => $this->line?->code,
        ];
    }
}
