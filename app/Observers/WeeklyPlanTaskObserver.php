<?php

namespace App\Observers;

use App\Errors\BadRequestError;
use App\Models\WeeklyPlanTask;
use App\Models\WeeklyPlanTaskLog;

class WeeklyPlanTaskObserver
{
    /**
     * Handle the WeeklyPlanTask "creating" event.
     */
    public function creating(WeeklyPlanTask $weeklyPlanTask): void
    {
        $this->ensureAuthenticatedUser();
    }

    /**
     * Handle the WeeklyPlanTask "created" event.
     */
    public function created(WeeklyPlanTask $weeklyPlanTask): void
    {
        WeeklyPlanTaskLog::create([
            'weekly_plan_task_id' => $weeklyPlanTask->id,
            'user_id' => auth()->user()->id,
            'event' => 'created',
            'field' => null,
            'old_value' => null,
            'new_value' => null,
        ]);
    }

    /**
     * Handle the WeeklyPlanTask "updating" event.
     */
    public function updating(WeeklyPlanTask $weeklyPlanTask): void
    {
        if (array_intersect(array_keys($weeklyPlanTask->getDirty()), WeeklyPlanTask::LOGGED_FIELDS)) {
            $this->ensureAuthenticatedUser();
        }
    }

    /**
     * Handle the WeeklyPlanTask "updated" event.
     */
    public function updated(WeeklyPlanTask $weeklyPlanTask): void
    {
        foreach ($weeklyPlanTask->getChanges() as $field => $newValue) {
            if (! in_array($field, WeeklyPlanTask::LOGGED_FIELDS, true)) {
                continue;
            }

            WeeklyPlanTaskLog::create([
                'weekly_plan_task_id' => $weeklyPlanTask->id,
                'user_id' => auth()->user()->id,
                'event' => 'updated',
                'field' => $field,
                'old_value' => $this->stringifyValue($weeklyPlanTask->getOriginal($field)),
                'new_value' => $this->stringifyValue($newValue),
            ]);
        }
    }

    /**
     * Abort the operation when there is no authenticated user to attribute the change to.
     */
    private function ensureAuthenticatedUser(): void
    {
        if (! auth()->user()) {
            throw new BadRequestError('No hay un usuario autenticado para registrar el cambio');
        }
    }

    /**
     * Cast a logged value to text, keeping real nulls as null.
     */
    private function stringifyValue(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
