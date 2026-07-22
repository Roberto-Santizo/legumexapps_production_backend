<?php

namespace App\Http\Resources\WeeklyPlans;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeeklyPlanResource extends JsonResource
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
            'week'=>        $this->week,
            'year'=>        $this->year
        ];
    }
}
