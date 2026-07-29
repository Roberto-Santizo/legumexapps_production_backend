<?php

namespace App\Services\DraftWeeklyPlans;

use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Interfaces\DraftWeeklyPlans\DraftWeeklyPlansServiceInterface;
use App\Models\DraftWeeklyPlan;
use Override;

class DraftWeeklyPlansService implements DraftWeeklyPlansServiceInterface
{
    #[Override]
    public function createDraftWeeklyPlan(array $data)
    {
        $plan = DraftWeeklyPlan::where('week', '=', $data['week'])->where('year', '=', $data['year'])->first();
        if ($plan) {
            throw new BadRequestError('El plan semanal especificado ya existe');
        }

        return DraftWeeklyPlan::create($data);
    }

    #[Override]
    public function getDraftWeeklyPlans(?string $limit)
    {
        $query = DraftWeeklyPlan::query();
        $query->orderBy('year', 'DESC')->orderBy('week', 'DESC');

        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->get();
    }

    #[Override]
    public function getDraftWeeklyPlanById(string $id)
    {
        $draftWeeklyPlan = DraftWeeklyPlan::find($id);
        if (! $draftWeeklyPlan) {
            throw new NotFoundError('El plan borrador no existe');
        }

        return $draftWeeklyPlan;
    }

    #[Override]
    public function updateDraftWeeklyPlanById(string $id, array $data)
    {
        $draftWeeklyPlan = $this->getDraftWeeklyPlanById($id);
        $draftWeeklyPlan->update($data);

        return true;
    }

    #[Override]
    public function deleteDraftWeeklyPlanById(string $id)
    {
        $draftWeeklyPlan = $this->getDraftWeeklyPlanById($id);
        $draftWeeklyPlan->delete();

        return true;
    }

    #[Override]
    public function confirmDraftWeeklyPlanById(string $id)
    {
        $draftWeeklyPlan = $this->getDraftWeeklyPlanById($id);
        $draftWeeklyPlan->forceFill(['confirmation_date' => now()])->save();

        return true;
    }

    #[Override]
    public function getHoursPerLine(string $id)
    {
        $plan = $this->getDraftWeeklyPlanById($id);

        return $plan->tasks()
            ->with('line')
            ->get()
            ->filter(fn ($task) => $task->line_id !== null)
            ->groupBy('line_id')
            ->map(fn ($tasks) => [
                'label' => $tasks->first()->line->name,
                'value' => $tasks->sum('hours'),
            ])
            ->values();
    }
}
