<?php

namespace App\Interfaces\WeeklyPlanTaskEmployees;

interface WeeklyPlanTaskEmployeesServiceInterface
{
    public function getAvailableEmployees(string $taskId);
}
