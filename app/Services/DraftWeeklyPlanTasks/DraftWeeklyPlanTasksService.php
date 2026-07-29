<?php

namespace App\Services\DraftWeeklyPlanTasks;

use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Interfaces\DraftWeeklyPlanTasks\DraftWeeklyPlanTasksServiceInterface;
use App\Models\DraftWeeklyPlanTask;
use App\Models\LineSku;
use Illuminate\Http\Request;
use Override;

class DraftWeeklyPlanTasksService implements DraftWeeklyPlanTasksServiceInterface
{
    #[Override]
    public function createDraftWeeklyPlanTask(array $data)
    {
        $hours = isset($data['line_id']) ? $this->calculateHours($data) : 0;

        $payload = [
            'boxes' => $data['boxes'],
            'hours' => $hours,
            'destination' => $data['destination'],
            'operation_date' => $data['operation_date'] ?? null,
            'draft_weekly_plan_id' => $data['draft_weekly_plan_id'],
            'line_id' => $data['line_id'] ?? null,
            'sku_id' => $data['sku_id'],
        ];

        return DraftWeeklyPlanTask::create($payload);
    }

    #[Override]
    public function getDraftWeeklyPlanTasks(?string $limit, Request $request)
    {
        $query = DraftWeeklyPlanTask::query();
        $query->with(['sku', 'line']);

        if ($request->query('draftWeeklyPlanId')) {
            $query->where('draft_weekly_plan_id', $request->query('draftWeeklyPlanId'));
        }
        if ($request->query('noOperationDate')) {
            $query->whereNull('operation_date');
        }

        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->get();
    }

    #[Override]
    public function getDraftWeeklyPlanTaskById(string $id)
    {
        $draftWeeklyPlanTask = DraftWeeklyPlanTask::find($id);
        if (! $draftWeeklyPlanTask) {
            throw new NotFoundError('La tarea del plan borrador no existe');
        }

        return $draftWeeklyPlanTask;
    }

    #[Override]
    public function updateDraftWeeklyPlanTaskById(string $id, array $data)
    {
        $draftWeeklyPlanTask = $this->getDraftWeeklyPlanTaskById($id);

        $data['hours'] = isset($data['line_id']) ? $this->calculateHours($data) : 0;

        $draftWeeklyPlanTask->update($data);

        return true;
    }

    #[Override]
    public function deleteDraftWeeklyPlanTaskById(string $id)
    {
        $draftWeeklyPlanTask = $this->getDraftWeeklyPlanTaskById($id);
        $draftWeeklyPlanTask->delete();

        return true;
    }

    /**
     * @param  array{sku_id: int, line_id: int, boxes: int}  $data
     */
    private function calculateHours(array $data): float
    {
        $performance = LineSku::query()
            ->select(['id', 'sku_id', 'line_id', 'lbs_performance'])
            ->where(['sku_id' => $data['sku_id'], 'line_id' => $data['line_id']])
            ->with(['sku:id,presentation'])
            ->first();

        if (! $performance) {
            throw new BadRequestError('El SKU no esta relacionado a la línea seleccionada');
        }

        $totalLbs = $data['boxes'] * $performance->sku->presentation;

        return $totalLbs / $performance->lbs_performance;
    }
}
