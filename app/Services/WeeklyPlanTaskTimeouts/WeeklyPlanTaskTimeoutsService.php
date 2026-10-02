<?php

namespace App\Services\WeeklyPlanTaskTimeouts;

use App\Interfaces\WeeklyPlanTaskTimeouts\WeeklyPlanTaskTimeoutsServiceInterface;
use App\Interfaces\WeeklyPlanTasks\WeeklyPlanTasksServiceInterface;

class WeeklyPlanTaskTimeoutsService implements WeeklyPlanTaskTimeoutsServiceInterface
{
    public function __construct(private WeeklyPlanTasksServiceInterface $weeklyPlanTasksService) {}
}
