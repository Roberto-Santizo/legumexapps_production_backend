<?php

namespace App\Services\WeeklyPlanTaskTimeouts;

use App\Errors\BadRequestError;
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
