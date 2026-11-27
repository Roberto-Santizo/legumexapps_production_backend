<?php

namespace App\Http\Resources\Positions;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PositionResource extends JsonResource
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
            'code' => $this->code,
            'activity_id' => $this->activity_id,
            'activity' => $this->activity?->name,
            'unit' => $this->activity?->unit,
            'line_sku_id' => $this->line_sku_id,
            'line_id' => $this->lineSku->line_id,
            'line' => $this->lineSku->line->name,
            'sku' => $this->lineSku->sku->code,
            'status' => $this->status,
        ];
    }
}
