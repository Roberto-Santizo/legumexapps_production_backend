<?php

namespace App\Services\WeeklyPlanTaskProductRecords;

use App\Calculators\ProductCalculator;
use App\Enums\CaptureType;
use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Interfaces\WeeklyPlanTaskProductRecords\WeeklyPlanTaskProductRecordsServiceInterface;
use App\Interfaces\WeeklyPlanTasks\WeeklyPlanTasksServiceInterface;
use App\Models\LineField;
use App\Models\WeeklyPlanTask;
use App\Models\WeeklyPlanTaskProductRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Override;

class WeeklyPlanTaskProductRecordsService implements WeeklyPlanTaskProductRecordsServiceInterface
{
    public function __construct(
        private WeeklyPlanTasksServiceInterface $weeklyPlanTasksService,
        private ProductCalculator $productCalculator,
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
        $this->ensureTaskInProgress($task);
        $lineFields = $this->weeklyPlanTasksService->getCaptureLineFields($task, CaptureType::Product);

        $columns = Arr::only($data['values'], WeeklyPlanTaskProductRecord::SYSTEM_KEYS);
        $extraValues = Arr::except($data['values'], WeeklyPlanTaskProductRecord::SYSTEM_KEYS);
        $this->ensureWeightsAreConsistent($columns);

        return WeeklyPlanTaskProductRecord::create([
            ...$columns,
            ...$this->calculate($lineFields, $columns),
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
        $task = $record->task;
        $this->ensureTaskInProgress($task);
        $lineFields = $this->weeklyPlanTasksService->getCaptureLineFields($task, CaptureType::Product);

        $columns = [
            ...$record->only(WeeklyPlanTaskProductRecord::SYSTEM_KEYS),
            ...Arr::only($data['values'], WeeklyPlanTaskProductRecord::SYSTEM_KEYS),
        ];
        $extraValues = [
            ...($record->extra_values ?? []),
            ...Arr::except($data['values'], WeeklyPlanTaskProductRecord::SYSTEM_KEYS),
        ];
        $this->ensureWeightsAreConsistent($columns);

        $record->update([
            ...$columns,
            ...$this->calculate($lineFields, $columns),
            'extra_values' => $extraValues ?: null,
        ]);

        return true;
    }

    #[Override]
    public function deleteWeeklyPlanTaskProductRecordById(string $id)
    {
        $record = $this->getWeeklyPlanTaskProductRecordById($id);
        $this->ensureTaskInProgress($record->task);

        $record->delete();

        return true;
    }

    /**
     * Product records can only be registered, modified or deleted while the task is in progress.
     */
    private function ensureTaskInProgress(WeeklyPlanTask $task): void
    {
        if ($task->status != 4) {
            throw new BadRequestError('Solo se pueden registrar productos en una tarea en progreso');
        }
    }

    /**
     * The classified pounds and the trimmed plus rejected pounds can not exceed the raw material,
     * and the packed pounds can not exceed the trimmed pounds.
     *
     * @param  array<string, mixed>  $columns
     */
    private function ensureWeightsAreConsistent(array $columns): void
    {
        $rawLbs = $columns['raw_lbs'] ?? null;
        $classifiedLbs = $columns['classified_lbs'] ?? null;
        $trimmedLbs = $columns['trimmed_lbs'] ?? null;
        $rejectedLbs = $columns['rejected_lbs'] ?? null;
        $packedLbs = $columns['packed_lbs'] ?? null;

        if ($classifiedLbs !== null && $rawLbs !== null && (float) $classifiedLbs > (float) $rawLbs) {
            throw new BadRequestError('Las libras clasificadas no pueden superar las libras de materia prima');
        }

        $hasProcessedLbs = $trimmedLbs !== null || $rejectedLbs !== null;

        if ($rawLbs !== null && $hasProcessedLbs && (float) $trimmedLbs + (float) $rejectedLbs > (float) $rawLbs) {
            throw new BadRequestError('Las libras recortadas y el rechazo no pueden superar las libras de materia prima');
        }

        if ($packedLbs !== null && $trimmedLbs !== null && (float) $packedLbs > (float) $trimmedLbs) {
            throw new BadRequestError('Lo empacado no puede superar las libras recortadas');
        }
    }

    /**
     * Resolve the calculated fields with the current configuration of the line.
     *
     * @param  Collection<int, LineField>  $lineFields
     * @param  array<string, mixed>  $columns
     * @return array{recovery_pct: ?float, rejection_pct: ?float}
     */
    private function calculate(Collection $lineFields, array $columns): array
    {
        $assignedKeys = $lineFields->map(fn (LineField $lineField) => $lineField->captureField->key)->all();

        return $this->productCalculator->calculate($columns, $assignedKeys);
    }
}
