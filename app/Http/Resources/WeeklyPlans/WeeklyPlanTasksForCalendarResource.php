<?php

namespace App\Http\Resources\WeeklyPlans;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class WeeklyPlanTasksForCalendarResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        return $this->groupBy('operation_date')
            ->flatMap(fn (Collection $tasks, string $date) => $this->buildEventsForDate($tasks, $date))
            ->values()
            ->all();
    }

    /**
     * Build one calendar event per production line for the given operation date.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildEventsForDate(Collection $tasks, string $date): array
    {
        $date = Carbon::parse($date);

        return $tasks->groupBy(fn ($task) => $task->performance->line->code)
            ->map(fn (Collection $lineTasks, string $lineCode) => $this->buildEvent($lineCode, $lineTasks, $date))
            ->values()
            ->all();
    }

    /**
     * Build a single calendar event representing a line's total worked hours.
     *
     * @return array<string, mixed>
     */
    private function buildEvent(string $lineCode, Collection $tasks, Carbon $date): array
    {
        $hours = round($this->calculateHours($tasks), 2);

        return [
            'id' => uniqid(),
            'title' => "{$lineCode} | {$hours} h",
            'date' => $date->format('Y-m-d'),
            'color' => '#FFFF',
        ];
    }

    /**
     * Sum the worked hours across the given tasks based on each task's performance rate.
     */
    private function calculateHours(Collection $tasks): float
    {
        return $tasks->sum(function ($task) {
            $total_lbs = $task->boxes * $task->performance->sku->presentation;

            return $task->performance->lbs_performance > 0
                ? $total_lbs / $task->performance->lbs_performance
                : 0;
        });
    }
}
