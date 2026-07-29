<?php

namespace App\Interfaces\DraftWeeklyPlanTasks;

use Illuminate\Http\Request;

interface DraftWeeklyPlanTasksServiceInterface
{
    public function createDraftWeeklyPlanTask(array $data);

    public function getDraftWeeklyPlanTasks(?string $limit, Request $request);

    public function getDraftWeeklyPlanTaskById(string $id);

    public function updateDraftWeeklyPlanTaskById(string $id, array $data);

    public function deleteDraftWeeklyPlanTaskById(string $id);
}
