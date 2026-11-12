<?php

namespace App\Http\Resources\Activities;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaginatedActivitiesResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $activities = ActivityResource::collection($this->items());

        return [
            'data' => $activities,
            'total' => $this->total(),
            'currentPage' => $this->currentPage(),
            'lastPage' => $this->lastPage(),
        ];
    }
}
