<?php

namespace App\Http\Resources\BiometricQueries;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BiometricLineEmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this['id'],
            'name' => $this['name'],
            'code' => $this['pin'],
            'position' => $this['last_name'],
        ];
    }
}
