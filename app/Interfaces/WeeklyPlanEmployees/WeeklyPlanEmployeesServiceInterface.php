<?php

namespace App\Interfaces\WeeklyPlanEmployees;

use Illuminate\Http\Request;

interface WeeklyPlanEmployeesServiceInterface
{
    public function createWeeklyPlanEmployee(array $data);

    public function getWeeklyPlanEmployees(?string $limit, Request $request);

    public function getWeeklyPlanEmployeeById(string $id);

    public function updateWeeklyPlanEmployeeById(array $data, string $id);

    public function deleteWeeklyPlanEmployeeById(string $id);

    public function uploadFile(mixed $file);
}
