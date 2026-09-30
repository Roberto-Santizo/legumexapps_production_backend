<?php

namespace App\Interfaces\WeeklyPlans;

use Illuminate\Http\Request;

interface WeeklyPlansServiceInterface
{
    public function getWeeklyPlans(?string $limit, Request $request);

    public function getWeeklyPlanById(string $id);

    public function getWeeklyPlanSummaryToday(string $id, ?string $date = null);

    public function getWeeklyPlanTasksByPlanId(string $id);
}
