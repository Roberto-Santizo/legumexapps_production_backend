<?php

namespace App\Services\WeeklyPlanTaskTimeouts;

use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Interfaces\WeeklyPlanTaskTimeouts\WeeklyPlanTaskTimeoutsServiceInterface;
use App\Interfaces\WeeklyPlanTasks\WeeklyPlanTasksServiceInterface;
use App\Models\WeeklyPlanTask;
use App\Models\WeeklyPlanTaskTimeout;
use Illuminate\Support\Facades\DB;
use Override;

class WeeklyPlanTaskTimeoutsService implements WeeklyPlanTaskTimeoutsServiceInterface
{
    public function __construct(private WeeklyPlanTasksServiceInterface $weeklyPlanTasksService) {}

    #[Override]
    public function getTaskTimeouts(string $taskId)
    {
        $task = $this->weeklyPlanTasksService->getWeeklyPlanTaskById($taskId);

        return $task->timeouts()
            ->with(['timeout', 'user'])
            ->orderBy('start_date')
            ->get();
    }

    /**
     * Open a timeout on a task in progress, revalidating the status and the open timeout under a row lock.
     *
     * @param  array{timeout_id: int, observation?: string|null}  $data
     */
    #[Override]
    public function startTimeout(string $taskId, array $data)
    {
        $task = $this->weeklyPlanTasksService->getWeeklyPlanTaskById($taskId);
        $this->ensureTaskInProgress($task);
        $this->ensureNoOpenTimeout($task);

        return DB::transaction(function () use ($task, $data) {
            $lockedTask = WeeklyPlanTask::lockForUpdate()->find($task->id);
            $this->ensureTaskInProgress($lockedTask);
            $this->ensureNoOpenTimeout($lockedTask);

            return WeeklyPlanTaskTimeout::create([
                'weekly_plan_task_id' => $lockedTask->id,
                'timeout_id' => $data['timeout_id'],
                'user_id' => auth()->user()->id,
                'start_date' => now(),
                'observation' => $data['observation'] ?? null,
            ]);
        });
    }

    /**
     * Close an open timeout with the server time and store its duration in hours.
     * The observation is replaced only when a non null one is sent.
     *
     * @param  array{observation?: string|null}  $data
     */
    #[Override]
    public function endTimeout(string $id, array $data)
    {
        $timeout = $this->getTimeoutById($id);
        $this->ensureTaskInProgress($timeout->task);

        if ($timeout->end_date !== null) {
            throw new BadRequestError('El tiempo muerto ya fue finalizado');
        }

        $endDate = now()->startOfSecond();

        $timeout->update([
            'end_date' => $endDate,
            'duration_hours' => round($timeout->start_date->diffInHours($endDate), 4),
            'observation' => $data['observation'] ?? $timeout->observation,
        ]);

        return $timeout;
    }

    private function getTimeoutById(string $id): WeeklyPlanTaskTimeout
    {
        $timeout = WeeklyPlanTaskTimeout::with('task')->find($id);

        if (! $timeout) {
            throw new NotFoundError('El tiempo muerto no existe');
        }

        return $timeout;
    }

    /**
     * Timeouts can only be registered, modified or deleted while the task is in progress.
     */
    private function ensureTaskInProgress(WeeklyPlanTask $task): void
    {
        if ($task->status != 4) {
            throw new BadRequestError('Solo se pueden registrar tiempos muertos en una tarea en progreso');
        }
    }

    /**
     * A task can only have one open timeout at a time.
     */
    private function ensureNoOpenTimeout(WeeklyPlanTask $task): void
    {
        if ($task->openTimeout()->exists()) {
            throw new BadRequestError('La tarea ya tiene un tiempo muerto abierto');
        }
    }
}
