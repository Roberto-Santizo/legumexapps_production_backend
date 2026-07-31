<?php

namespace App\Http\Resources\WeeklyPlanTasks;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeeklyPlanTaskPackingMaterialItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        $sku = $this->performance->sku;

        return $sku->packingMaterialItems->map(fn ($recipeItem) => [
            'quantity' => ($this->boxes * $sku->presentation) / $recipeItem->lbs_per_item,
            'lote' => '',
            'destination' => '',
            'packing_material_id' => $recipeItem->packing_material_id,
            'packing_material_name' => $recipeItem->item?->name,
            'packing_material_code' => $recipeItem->item?->code,
            'lbs_per_item' => $recipeItem->lbs_per_item,
        ])->all();
    }
}
