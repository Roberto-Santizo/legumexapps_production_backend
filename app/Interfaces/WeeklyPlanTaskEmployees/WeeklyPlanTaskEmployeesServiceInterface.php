<?php

namespace App\Interfaces\WeeklyPlanTaskEmployees;

interface WeeklyPlanTaskEmployeesServiceInterface
{
    public function getAvailableEmployees(string $taskId);

    public function getTaskEmployees(string $taskId);

    public function confirmEmployees(string $taskId, array $data);

    public function addEmployee(string $taskId, int $weeklyPlanEmployeeId);
}
