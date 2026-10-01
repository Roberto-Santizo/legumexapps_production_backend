<?php

namespace App\Services\WeeklyPlanTaskEmployees;

use App\Errors\BadRequestError;
use App\Interfaces\WeeklyPlanTaskEmployees\WeeklyPlanTaskEmployeesServiceInterface;
use App\Interfaces\WeeklyPlanTasks\WeeklyPlanTasksServiceInterface;
use App\Models\Position;
use App\Models\WeeklyPlanEmployee;
use App\Models\WeeklyPlanTask;
use App\Models\WeeklyPlanTaskEmployee;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Override;

class WeeklyPlanTaskEmployeesService implements WeeklyPlanTaskEmployeesServiceInterface
{
    public function __construct(private WeeklyPlanTasksServiceInterface $weeklyPlanTasksService) {}

    #[Override]
    public function getAvailableEmployees(string $taskId)
    {
        $task = $this->weeklyPlanTasksService->getWeeklyPlanTaskById($taskId);

        return $this->getCandidates($task);
    }

    #[Override]
    public function getTaskEmployees(string $taskId)
    {
        $task = $this->weeklyPlanTasksService->getWeeklyPlanTaskById($taskId);

        return $task->employees()
            ->with(['weeklyPlanEmployee.employee', 'position', 'replacedWeeklyPlanEmployee.employee'])
            ->get();
    }

    #[Override]
    public function confirmEmployees(string $taskId, array $data)
    {
        $task = $this->weeklyPlanTasksService->getWeeklyPlanTaskById($taskId);
        $this->ensureTaskAwaitsEmployeeConfirmation($task);

        $now = now();
        $assignmentsToCreate = $this->getCandidates($task)
            ->map(fn (WeeklyPlanEmployee $candidate) => [
                'weekly_plan_task_id' => $task->id,
                'weekly_plan_employee_id' => $candidate->id,
                'position_id' => $candidate->position_id,
                'replaced_weekly_plan_employee_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        DB::transaction(function () use ($task, $assignmentsToCreate) {
            $lockedTask = WeeklyPlanTask::lockForUpdate()->find($task->id);
            $this->ensureTaskAwaitsEmployeeConfirmation($lockedTask);

            WeeklyPlanTaskEmployee::insert($assignmentsToCreate);

            $lockedTask->status = 3;
            $lockedTask->save();
        });

        return null;
    }

    /**
     * Confirmation is only allowed once, while the task is in status 2.
     */
    private function ensureTaskAwaitsEmployeeConfirmation(WeeklyPlanTask $task): void
    {
        if ($task->status != 2) {
            throw new BadRequestError('La tarea no está lista para confirmar asignaciones');
        }

        if ($task->employees()->exists()) {
            throw new BadRequestError('La tarea ya tiene personal asignado');
        }
    }

    /**
     * Employees of the task's weekly plan whose active position belongs to the task's line.
     *
     * @return Collection<int, WeeklyPlanEmployee>
     */
    private function getCandidates(WeeklyPlanTask $task): Collection
    {
        $lineId = $task->performance->line_id;

        return WeeklyPlanEmployee::with(['employee', 'position'])
            ->where('weekly_plan_id', $task->weekly_plan_id)
            ->whereHas('position', function ($query) use ($lineId) {
                $query->where('line_id', $lineId)->where('status', 1);
            })
            ->orderBy(
                Position::select('code')->whereColumn('positions.id', 'weekly_plan_employees.position_id')
            )
            ->get();
    }
}
