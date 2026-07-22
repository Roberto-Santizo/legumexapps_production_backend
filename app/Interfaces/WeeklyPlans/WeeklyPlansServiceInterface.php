<?php

namespace App\Interfaces\WeeklyPlans;

interface WeeklyPlansServiceInterface
{
    public function getWeeklyPlans(?string $limit);

    public function getWeeklyPlanById(string $id);
}
