<?php

namespace App\Http\Resources\WeeklyPlans;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class WeeklyPlanSummaryTodayResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        return $this->groupBy(fn ($task) => $task->performance->line->id)
            ->map(fn (Collection $tasks) => $this->buildLineSummary($tasks))
            ->sortBy('line_code')
            ->values()
            ->all();
    }

    /**
     * Build the task count summary for a single production line.
     *
     * @return array<string, mixed>
     */
    private function buildLineSummary(Collection $tasks): array
    {
        $line = $tasks->first()->performance->line;

        return [
            'line_id' => $line->id,
            'line_code' => $line->code,
            'line_name' => $line->name,
            'total_tasks' => $tasks->count(),
        ];
    }
}
