<?php

namespace App\Http\Resources\LineSkus;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LineSkuResource extends JsonResource
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
            'sku' => $this->sku->code,
            'sku_id' => $this->sku_id,
            'line' => $this->line->name,
            'line_id' => $this->line_id,
            'lbs_performance' => $this->lbs_performance,
            'accepted_percentage' => $this->accepted_percentage,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
        ];
    }
}
