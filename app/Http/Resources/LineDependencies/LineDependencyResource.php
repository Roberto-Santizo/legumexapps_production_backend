<?php

namespace App\Http\Resources\LineDependencies;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LineDependencyResource extends JsonResource
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
            'line_dependant_name' => $this->dependantLine->name,
            'positions' => $this->dependantLine->positions->count()
        ];
    }
}
