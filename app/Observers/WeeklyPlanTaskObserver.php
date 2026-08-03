<?php

namespace App\Observers;

use App\Errors\BadRequestError;
use App\Helpers\MailHandler;
use App\Mail\WeeklyPlanTaskChanged;
use App\Models\WeeklyPlanTask;
use App\Models\WeeklyPlanTaskLog;

class WeeklyPlanTaskObserver
{
    /**
     * Whether the individual change email is sent on create and update.
     */
    public static bool $sendsMail = true;

    /**
     * Run a callback with the individual change email disabled.
     */
    public static function withoutMail(callable $callback): mixed
    {
        self::$sendsMail = false;

        try {
            return $callback();
        } finally {
            self::$sendsMail = true;
        }
    }

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

        if (! self::$sendsMail) {
            return;
        }

        $changes = [];

        foreach (WeeklyPlanTask::LOGGED_FIELDS as $field) {
            $changes[] = [
                'field' => $field,
                'old' => null,
                'new' => $this->stringifyValue($weeklyPlanTask->{$field}),
            ];
        }

        MailHandler::notifyWeeklyPlanTaskRecipients(new WeeklyPlanTaskChanged(
            event: 'created',
            taskId: $weeklyPlanTask->id,
            userName: auth()->user()->name,
            changedAt: now(),
            changes: $changes,
        ));
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
        $changes = [];

        foreach ($weeklyPlanTask->getChanges() as $field => $newValue) {
            if (! in_array($field, WeeklyPlanTask::LOGGED_FIELDS, true)) {
                continue;
            }

            $oldValue = $this->stringifyValue($weeklyPlanTask->getOriginal($field));
            $newValue = $this->stringifyValue($newValue);

            WeeklyPlanTaskLog::create([
                'weekly_plan_task_id' => $weeklyPlanTask->id,
                'user_id' => auth()->user()->id,
                'event' => 'updated',
                'field' => $field,
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ]);

            $changes[] = [
                'field' => $field,
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        if (! self::$sendsMail || $changes === []) {
            return;
        }

        MailHandler::notifyWeeklyPlanTaskRecipients(new WeeklyPlanTaskChanged(
            event: 'updated',
            taskId: $weeklyPlanTask->id,
            userName: auth()->user()->name,
            changedAt: now(),
            changes: $changes,
        ));
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
