<?php

namespace App\Http\Resources\SkuPackingMaterials;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SkuPackingMaterialResource extends JsonResource
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
            'lbs_per_item' => $this->lbs_per_item,
            'sku' => $this->sku->code,
            'sku_id' => $this->sku_id,
            'packing_material' => $this->item->name,
            'packing_material_code'=> $this->item->code,
            'packing_material_id' => $this->packing_material_id,
            'created_at' => $this->created_at->format('d-m-Y h:i:s A'),
            'updated_at' => $this->updated_at->format('d-m-Y h:i:s A')
        ];
    }
}
