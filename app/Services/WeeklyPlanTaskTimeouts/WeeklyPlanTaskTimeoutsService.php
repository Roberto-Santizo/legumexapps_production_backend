<?php

namespace App\Services\WeeklyPlanTaskTimeouts;

use App\Interfaces\WeeklyPlanTaskTimeouts\WeeklyPlanTaskTimeoutsServiceInterface;
use App\Interfaces\WeeklyPlanTasks\WeeklyPlanTasksServiceInterface;
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
}
