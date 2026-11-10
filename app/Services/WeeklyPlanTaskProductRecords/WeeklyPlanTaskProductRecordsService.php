<?php

namespace App\Services\WeeklyPlanTaskProductRecords;

use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Interfaces\WeeklyPlanTaskProductRecords\WeeklyPlanTaskProductRecordsServiceInterface;
use App\Interfaces\WeeklyPlanTasks\WeeklyPlanTasksServiceInterface;
use App\Models\WeeklyPlanTaskProductRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Override;

class WeeklyPlanTaskProductRecordsService implements WeeklyPlanTaskProductRecordsServiceInterface
{
    public function __construct(
        private WeeklyPlanTasksServiceInterface $weeklyPlanTasksService,
    ) {}

    #[Override]
    public function getWeeklyPlanTaskProductRecords(Request $request)
    {
        $weeklyPlanTaskId = $request->query('weeklyPlanTaskId');

        if (! $weeklyPlanTaskId) {
            throw new BadRequestError('El id de la tarea del plan semanal es obligatorio');
        }

        return WeeklyPlanTaskProductRecord::with('user')
            ->where('weekly_plan_task_id', $weeklyPlanTaskId)
            ->orderBy('created_at')
            ->get();
    }

    #[Override]
    public function createWeeklyPlanTaskProductRecord(array $data)
    {
        $task = $this->weeklyPlanTasksService->getWeeklyPlanTaskById($data['weekly_plan_task_id']);

        $columns = Arr::only($data['values'], WeeklyPlanTaskProductRecord::SYSTEM_KEYS);
        $extraValues = Arr::except($data['values'], WeeklyPlanTaskProductRecord::SYSTEM_KEYS);

        return WeeklyPlanTaskProductRecord::create([
            ...$columns,
            'weekly_plan_task_id' => $task->id,
            'user_id' => auth()->user()->id,
            'extra_values' => $extraValues ?: null,
        ]);
    }

    #[Override]
    public function getWeeklyPlanTaskProductRecordById(string $id)
    {
        $record = WeeklyPlanTaskProductRecord::with('user')->find($id);

        if (! $record) {
            throw new NotFoundError('El registro de producto no existe');
        }

        return $record;
    }

    #[Override]
    public function updateWeeklyPlanTaskProductRecordById(array $data, string $id)
    {
        $record = $this->getWeeklyPlanTaskProductRecordById($id);

        $columns = [
            ...$record->only(WeeklyPlanTaskProductRecord::SYSTEM_KEYS),
            ...Arr::only($data['values'], WeeklyPlanTaskProductRecord::SYSTEM_KEYS),
        ];
        $extraValues = [
            ...($record->extra_values ?? []),
            ...Arr::except($data['values'], WeeklyPlanTaskProductRecord::SYSTEM_KEYS),
        ];

        $record->update([
            ...$columns,
            'extra_values' => $extraValues ?: null,
        ]);

        return true;
    }

    #[Override]
    public function deleteWeeklyPlanTaskProductRecordById(string $id)
    {
        $record = $this->getWeeklyPlanTaskProductRecordById($id);

        $record->delete();

        return true;
    }
}
