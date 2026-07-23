<?php

namespace App\Interfaces\WeeklyPlanEmployees;

interface WeeklyPlanEmployeesServiceInterface
{
    public function createWeeklyPlanEmployee(array $data);

    public function getWeeklyPlanEmployees(?string $limit);

    public function getWeeklyPlanEmployeeById(string $id);

    public function updateWeeklyPlanEmployeeById(array $data, string $id);

    public function deleteWeeklyPlanEmployeeById(string $id);

    public function uploadFile(mixed $file);
}
