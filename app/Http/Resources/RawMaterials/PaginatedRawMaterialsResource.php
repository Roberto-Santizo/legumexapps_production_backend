<?php

namespace App\Http\Resources\RawMaterials;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaginatedRawMaterialsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = RawMaterialResource::collection($this->items());

        return [
            'data' => $items,
            'total' => $this->total(),
            'currentPage' => $this->currentPage(),
            'lastPage' => $this->lastPage(),
        ];
    }
}
