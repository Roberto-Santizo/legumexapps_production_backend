<?php

namespace App\Interfaces\WeeklyPlanTaskTimeouts;

interface WeeklyPlanTaskTimeoutsServiceInterface
{
    public function getTaskTimeouts(string $taskId);

    public function startTimeout(string $taskId, array $data);

    public function endTimeout(string $id, array $data);
}
