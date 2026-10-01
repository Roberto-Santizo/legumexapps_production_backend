<?php

namespace App\Services\WeeklyPlanTaskEmployees;

use App\Interfaces\WeeklyPlanTaskEmployees\WeeklyPlanTaskEmployeesServiceInterface;
use App\Interfaces\WeeklyPlanTasks\WeeklyPlanTasksServiceInterface;
use App\Models\Position;
use App\Models\WeeklyPlanEmployee;
use App\Models\WeeklyPlanTask;
use Illuminate\Database\Eloquent\Collection;
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
