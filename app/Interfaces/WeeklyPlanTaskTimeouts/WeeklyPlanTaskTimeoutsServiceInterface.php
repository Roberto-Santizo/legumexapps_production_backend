<?php

namespace App\Interfaces\WeeklyPlanTaskTimeouts;

interface WeeklyPlanTaskTimeoutsServiceInterface
{
    public function getTaskTimeouts(string $taskId);

    public function startTimeout(string $taskId, array $data);

    public function endTimeout(string $id, array $data);

    public function updateTimeout(string $id, array $data);

    public function deleteTimeout(string $id);
}
