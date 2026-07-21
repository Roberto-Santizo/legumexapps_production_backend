<?php

namespace App\Http\Resources\SkuPackingMaterials;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaginatedSkuPackingMaterialsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $skuPackingMaterials = SkuPackingMaterialResource::collection($this->items());

        return [
            'data' => $skuPackingMaterials,
            'total' => $this->total(),
            'currentPage' => $this->currentPage(),
            'lastPage' => $this->lastPage(),
        ];
    }
}
