<?php

namespace App\Services\WeeklyPlanTaskPerformanceRecords;

use App\Calculators\PalletCalculator;
use App\Enums\CaptureType;
use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Interfaces\WeeklyPlanTaskPerformanceRecords\WeeklyPlanTaskPerformanceRecordsServiceInterface;
use App\Interfaces\WeeklyPlanTasks\WeeklyPlanTasksServiceInterface;
use App\Models\LineField;
use App\Models\WeeklyPlanTask;
use App\Models\WeeklyPlanTaskPerformanceRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Override;

class WeeklyPlanTaskPerformanceRecordsService implements WeeklyPlanTaskPerformanceRecordsServiceInterface
{
    public function __construct(
        private WeeklyPlanTasksServiceInterface $weeklyPlanTasksService,
        private PalletCalculator $palletCalculator,
    ) {}

    #[Override]
    public function getWeeklyPlanTaskPerformanceRecords(Request $request)
    {
        $weeklyPlanTaskId = $request->query('weeklyPlanTaskId');

        if (! $weeklyPlanTaskId) {
            throw new BadRequestError('El id de la tarea del plan semanal es obligatorio');
        }

        return WeeklyPlanTaskPerformanceRecord::with('user')
            ->where('weekly_plan_task_id', $weeklyPlanTaskId)
            ->orderBy('created_at')
            ->get();
    }

    #[Override]
    public function createWeeklyPlanTaskPerformanceRecord(array $data)
    {
        $task = $this->weeklyPlanTasksService->getWeeklyPlanTaskById($data['weekly_plan_task_id']);
        $this->ensureTaskInProgress($task);
        $lineFields = $this->weeklyPlanTasksService->getCaptureLineFields($task, CaptureType::Pallet);

        $columns = Arr::only($data['values'], WeeklyPlanTaskPerformanceRecord::SYSTEM_KEYS);
        $extraValues = Arr::except($data['values'], WeeklyPlanTaskPerformanceRecord::SYSTEM_KEYS);
        $this->ensurePalletNumberIsAvailable($task, $columns['pallet_number'] ?? null);

        return WeeklyPlanTaskPerformanceRecord::create([
            ...$columns,
            ...$this->calculate($task, $lineFields, $columns),
            'weekly_plan_task_id' => $task->id,
            'user_id' => auth()->user()->id,
            'extra_values' => $extraValues ?: null,
        ]);
    }

    #[Override]
    public function getWeeklyPlanTaskPerformanceRecordById(string $id)
    {
        $record = WeeklyPlanTaskPerformanceRecord::with('user')->find($id);

        if (! $record) {
            throw new NotFoundError('La toma de rendimiento no existe');
        }

        return $record;
    }

    #[Override]
    public function updateWeeklyPlanTaskPerformanceRecordById(array $data, string $id)
    {
        $record = $this->getWeeklyPlanTaskPerformanceRecordById($id);
        $task = $record->task;
        $this->ensureTaskInProgress($task);
        $lineFields = $this->weeklyPlanTasksService->getCaptureLineFields($task, CaptureType::Pallet);

        $columns = [
            ...$record->only(WeeklyPlanTaskPerformanceRecord::SYSTEM_KEYS),
            ...Arr::only($data['values'], WeeklyPlanTaskPerformanceRecord::SYSTEM_KEYS),
        ];
        $extraValues = [
            ...($record->extra_values ?? []),
            ...Arr::except($data['values'], WeeklyPlanTaskPerformanceRecord::SYSTEM_KEYS),
        ];
        $this->ensurePalletNumberIsAvailable($task, $columns['pallet_number'], $record->id);

        $record->update([
            ...$columns,
            ...$this->calculate($task, $lineFields, $columns),
            'extra_values' => $extraValues ?: null,
        ]);

        return true;
    }

    #[Override]
    public function deleteWeeklyPlanTaskPerformanceRecordById(string $id)
    {
        $record = $this->getWeeklyPlanTaskPerformanceRecordById($id);
        $this->ensureTaskInProgress($record->task);

        $record->delete();

        return true;
    }

    /**
     * Performance records can only be registered, modified or deleted while the task is in progress.
     */
    private function ensureTaskInProgress(WeeklyPlanTask $task): void
    {
        if ($task->status != 4) {
            throw new BadRequestError('Solo se pueden registrar tomas de rendimiento en una tarea en progreso');
        }
    }

    /**
     * A pallet number can only be registered once per task; records without pallet are not restricted.
     */
    private function ensurePalletNumberIsAvailable(WeeklyPlanTask $task, ?int $palletNumber, ?int $ignoredRecordId = null): void
    {
        if ($palletNumber === null) {
            return;
        }

        $isTaken = $task->performanceRecords()
            ->where('pallet_number', $palletNumber)
            ->when($ignoredRecordId, fn ($query) => $query->whereKeyNot($ignoredRecordId))
            ->exists();

        if ($isTaken) {
            throw new BadRequestError("La pallet {$palletNumber} ya fue registrada en la tarea");
        }
    }

    /**
     * Resolve the calculated fields with the current configuration of the line and the SKU presentation.
     *
     * @param  Collection<int, LineField>  $lineFields
     * @param  array<string, mixed>  $columns
     * @return array{net_weight: ?float, ticket_weight: ?float, difference: ?float}
     */
    private function calculate(WeeklyPlanTask $task, Collection $lineFields, array $columns): array
    {
        $presentation = $task->performance?->sku?->presentation;
        $assignedKeys = $lineFields->map(fn (LineField $lineField) => $lineField->captureField->key)->all();

        return $this->palletCalculator->calculate($columns, $presentation !== null ? (float) $presentation : null, $assignedKeys);
    }
}
