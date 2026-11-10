<?php

namespace App\Http\Resources\WeeklyPlanTaskAssistant;

use App\Http\Resources\WeeklyPlanTasks\WeeklyPlanTaskResource;
use Illuminate\Http\Request;

class WeeklyPlanTaskAssistantTaskResource extends WeeklyPlanTaskResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'capture_type' => $this->performance->line->capture_type?->value,
            'week' => $this->weeklyPlan?->week,
            'year' => $this->weeklyPlan?->year,
        ];
    }
}
