<?php

namespace App\Services\WeeklyPlans;

use App\Errors\NotFoundError;
use App\Interfaces\WeeklyPlans\WeeklyPlansServiceInterface;
use App\Models\WeeklyPlan;
use Override;

class WeeklyPlansService implements WeeklyPlansServiceInterface
{
    #[Override]
    public function getWeeklyPlans(?string $limit)
    {
        $query = WeeklyPlan::query();

        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->get();
    }

    #[Override]
    public function getWeeklyPlanById(string $id)
    {
        $weeklyPlan = WeeklyPlan::find($id, ['*']);
        if (! $weeklyPlan) {
            throw new NotFoundError('El plan no existe');
        }

        return $weeklyPlan;

    }

    #[Override]
    public function getWeeklyPlanTasksByPlanId(string $id)
    {
        $plan = $this->getWeeklyPlanById($id);

        return $plan->tasks()->whereNotNull('operation_date')->get();
    }
}
