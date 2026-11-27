<?php

namespace App\Http\Resources\LineSkuActivities;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LineSkuActivityResource extends JsonResource
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
            'line_sku_id' => $this->line_sku_id,
            'activity_id' => $this->activity_id,
            'activity' => $this->activity->name,
            'unit' => $this->activity->unit,
            'quantity' => $this->quantity,
        ];
    }
}
