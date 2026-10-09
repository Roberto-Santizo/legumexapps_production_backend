<?php

namespace App\Interfaces\WeeklyPlanTasks;

use App\Enums\CaptureType;
use App\Models\WeeklyPlanTask;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface WeeklyPlanTasksServiceInterface
{
    public function createWeeklyPlanTask(array $data);

    public function getWeeklyPlanTasks(?string $limit, Request $request);

    public function getWeeklyPlanTaskById(string $id);

    public function updateWeeklyPlanTaskById(array $data, string $id);

    public function deleteWeeklyPlanTaskById(string $id);

    public function assignOperationDate(array $tasksIds, string $operationDate);

    public function splitWeeklyPlanTask(string $taskId, array $portions);

    public function getPackingMaterialItemsByTaskId(string $id);

    public function startWeeklyPlanTask(string $id);

    public function endWeeklyPlanTask(string $id, array $data);

    public function getCaptureLineFields(WeeklyPlanTask $task, CaptureType $captureType): Collection;
}
