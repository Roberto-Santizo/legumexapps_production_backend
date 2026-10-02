<?php

namespace App\Interfaces\WeeklyPlanTaskTimeouts;

interface WeeklyPlanTaskTimeoutsServiceInterface
{
    public function getTaskTimeouts(string $taskId);
}
