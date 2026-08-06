<?php

namespace App\Interfaces\WeeklyPlans;

interface WeeklyPlansServiceInterface
{
    public function getWeeklyPlans(?string $limit);

    public function getWeeklyPlanById(string $id);

    public function getWeeklyPlanSummaryToday(string $id, ?string $date = null);

    public function getWeeklyPlanTasksByPlanId(string $id);
}
