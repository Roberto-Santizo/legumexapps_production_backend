<?php

namespace App\Http\Resources\WeeklyPlanTasks;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaginatedWeeklyPlanTasksResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = WeeklyPlanTaskResource::collection($this->items());

        return [
            'data' => $items,
            'total' => $this->total(),
            'currentPage' => $this->currentPage(),
            'lastPage' => $this->lastPage(),
        ];
    }
}
