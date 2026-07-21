<?php

namespace App\Http\Resources\Positions;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PositionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' =>         $this->id,
            'code' =>       $this->code,
            'activity' =>   $this->activity,
            'line_id' =>    $this->line_id,
            'line'=>        $this->line->name,
            'status' =>     $this->status,
        ];
    }
}
