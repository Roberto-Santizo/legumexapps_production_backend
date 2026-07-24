<?php

namespace App\Interfaces\WeeklyPlanTasks;

use Illuminate\Http\Request;

interface WeeklyPlanTasksServiceInterface
{
    public function createWeeklyPlanTask(array $data);

    public function getWeeklyPlanTasks(?string $limit, Request $request);

    public function getWeeklyPlanTaskById(string $id);

    public function updateWeeklyPlanTaskById(array $data, string $id);

    public function deleteWeeklyPlanTaskById(string $id);
}
