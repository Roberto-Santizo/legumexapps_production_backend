<?php

namespace App\Interfaces\DraftWeeklyPlans;

interface DraftWeeklyPlansServiceInterface
{
    public function createDraftWeeklyPlan(array $data);

    public function getDraftWeeklyPlans(?string $limit);

    public function getDraftWeeklyPlanById(string $id);

    public function updateDraftWeeklyPlanById(string $id, array $data);

    public function deleteDraftWeeklyPlanById(string $id);

    public function confirmDraftWeeklyPlanById(string $id);

    public function getHoursPerLine(string $id);
}
