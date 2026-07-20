<?php

namespace App\Http\Resources\Skus;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SkuResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' =>                 $this->id,
            'code'=>                $this->code,
            'product_name'=>        $this->product_name,
            'presentation'=>        $this->presentation,
            'boxes_per_pallet'=>    $this->boxes_per_pallet,
            'client'=>              $this->client->name,
            'client_id'=>           $this->client_id,

        ];
    }
}
