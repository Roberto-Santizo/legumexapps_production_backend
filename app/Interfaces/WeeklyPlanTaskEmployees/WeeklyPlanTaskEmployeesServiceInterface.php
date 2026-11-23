<?php

namespace App\Interfaces\WeeklyPlanTaskEmployees;

interface WeeklyPlanTaskEmployeesServiceInterface
{
    public function getAvailableEmployees(string $taskId);

    public function getTaskEmployees(string $taskId);

    public function confirmEmployees(string $taskId, array $data);

    public function getLastEmployeesConfiguration(string $lineCode);

    public function confirmEmployeesByPosition(string $taskId, array $data);

    public function removeEmployee(string $assignmentId);

    public function updateEmployeeActivity(string $assignmentId, array $data);
}
