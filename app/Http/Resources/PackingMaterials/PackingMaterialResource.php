<?php

namespace App\Http\Resources\PackingMaterials;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackingMaterialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'=>              $this->id,
            'name'=>            $this->name,
            'code'=>            $this->code,
            'description'=>     $this->description,
            'blocked'=>         $this->blocked ? true : false
        ];
    }
}
