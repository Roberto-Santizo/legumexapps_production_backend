<?php

namespace App\Http\Resources\SkuRawMaterials;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SkuRawMaterialResource extends JsonResource
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
            'percentage' => $this->percentage,
            'sku' => $this->sku->code,
            'stock_keeping_unit_id' => $this->stock_keeping_unit_id,
            'raw_material' => $this->item->product_name,
            'raw_material_id' => $this->raw_material_id,
        ];
    }
}
