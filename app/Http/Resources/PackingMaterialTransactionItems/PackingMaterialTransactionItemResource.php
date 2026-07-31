<?php

namespace App\Http\Resources\PackingMaterialTransactionItems;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackingMaterialTransactionItemResource extends JsonResource
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
            'quantity' => $this->quantity,
            'lote' => $this->lote,
            'destination' => $this->destination,
            'packing_material_id' => $this->packing_material_id,
            'packing_material_name' => $this->packingMaterial?->name,
            'packing_material_code' => $this->packingMaterial?->code,
            'pm_transaction_id' => $this->pm_transaction_id,
        ];
    }
}
