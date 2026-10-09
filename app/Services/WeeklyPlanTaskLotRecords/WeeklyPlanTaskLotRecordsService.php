<?php

namespace App\Services\WeeklyPlanTaskLotRecords;

use App\Calculators\LotCalculator;
use App\Enums\CaptureType;
use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Interfaces\WeeklyPlanTaskLotRecords\WeeklyPlanTaskLotRecordsServiceInterface;
use App\Interfaces\WeeklyPlanTasks\WeeklyPlanTasksServiceInterface;
use App\Models\LineField;
use App\Models\WeeklyPlanTask;
use App\Models\WeeklyPlanTaskLotRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Override;

class WeeklyPlanTaskLotRecordsService implements WeeklyPlanTaskLotRecordsServiceInterface
{
    public function __construct(
        private WeeklyPlanTasksServiceInterface $weeklyPlanTasksService,
        private LotCalculator $lotCalculator,
    ) {}

    #[Override]
    public function getWeeklyPlanTaskLotRecords(Request $request)
    {
        $weeklyPlanTaskId = $request->query('weeklyPlanTaskId');

        if (! $weeklyPlanTaskId) {
            throw new BadRequestError('El id de la tarea del plan semanal es obligatorio');
        }

        return WeeklyPlanTaskLotRecord::with('user')
            ->where('weekly_plan_task_id', $weeklyPlanTaskId)
            ->orderBy('created_at')
            ->get();
    }

    #[Override]
    public function createWeeklyPlanTaskLotRecord(array $data)
    {
        $task = $this->weeklyPlanTasksService->getWeeklyPlanTaskById($data['weekly_plan_task_id']);
        $this->ensureTaskInProgress($task);
        $lineFields = $this->weeklyPlanTasksService->getCaptureLineFields($task, CaptureType::Lot);

        $columns = Arr::only($data['values'], WeeklyPlanTaskLotRecord::SYSTEM_KEYS);
        $extraValues = Arr::except($data['values'], WeeklyPlanTaskLotRecord::SYSTEM_KEYS);
        $this->ensureLotIsAvailable($task, $columns['lot'] ?? null);
        $this->ensureWeightsAreConsistent($columns);

        return WeeklyPlanTaskLotRecord::create([
            ...$columns,
            ...$this->calculate($lineFields, $columns),
            'weekly_plan_task_id' => $task->id,
            'user_id' => auth()->user()->id,
            'extra_values' => $extraValues ?: null,
        ]);
    }

    #[Override]
    public function getWeeklyPlanTaskLotRecordById(string $id)
    {
        $record = WeeklyPlanTaskLotRecord::with('user')->find($id);

        if (! $record) {
            throw new NotFoundError('El registro de lote no existe');
        }

        return $record;
    }

    #[Override]
    public function updateWeeklyPlanTaskLotRecordById(array $data, string $id)
    {
        $record = $this->getWeeklyPlanTaskLotRecordById($id);
        $task = $record->task;
        $this->ensureTaskInProgress($task);
        $lineFields = $this->weeklyPlanTasksService->getCaptureLineFields($task, CaptureType::Lot);

        $columns = [
            ...$record->only(WeeklyPlanTaskLotRecord::SYSTEM_KEYS),
            ...Arr::only($data['values'], WeeklyPlanTaskLotRecord::SYSTEM_KEYS),
        ];
        $extraValues = [
            ...($record->extra_values ?? []),
            ...Arr::except($data['values'], WeeklyPlanTaskLotRecord::SYSTEM_KEYS),
        ];
        $this->ensureLotIsAvailable($task, $columns['lot'], $record->id);
        $this->ensureWeightsAreConsistent($columns);

        $record->update([
            ...$columns,
            ...$this->calculate($lineFields, $columns),
            'extra_values' => $extraValues ?: null,
        ]);

        return true;
    }

    #[Override]
    public function deleteWeeklyPlanTaskLotRecordById(string $id)
    {
        $record = $this->getWeeklyPlanTaskLotRecordById($id);
        $this->ensureTaskInProgress($record->task);

        $record->delete();

        return true;
    }

    /**
     * Lot records can only be registered, modified or deleted while the task is in progress.
     */
    private function ensureTaskInProgress(WeeklyPlanTask $task): void
    {
        if ($task->status != 4) {
            throw new BadRequestError('Solo se pueden registrar lotes en una tarea en progreso');
        }
    }

    /**
     * A lot can only be registered once per task; records without lot are not restricted.
     */
    private function ensureLotIsAvailable(WeeklyPlanTask $task, ?string $lot, ?int $ignoredRecordId = null): void
    {
        if ($lot === null) {
            return;
        }

        $isTaken = $task->lotRecords()
            ->where('lot', $lot)
            ->when($ignoredRecordId, fn ($query) => $query->whereKeyNot($ignoredRecordId))
            ->exists();

        if ($isTaken) {
            throw new BadRequestError("El lote {$lot} ya fue registrado en la tarea");
        }
    }

    /**
     * The applied raw material can not exceed the intake, and the trimmed plus overripe pounds can not exceed the applied raw material.
     *
     * @param  array<string, mixed>  $columns
     */
    private function ensureWeightsAreConsistent(array $columns): void
    {
        $intakeLbs = $columns['intake_lbs'] ?? null;
        $appliedRawLbs = $columns['applied_raw_lbs'] ?? null;
        $trimmedLbs = $columns['trimmed_lbs'] ?? null;
        $overripeLbs = $columns['overripe_lbs'] ?? null;

        if ($appliedRawLbs !== null && $intakeLbs !== null && (float) $appliedRawLbs > (float) $intakeLbs) {
            throw new BadRequestError('La MP aplicada no puede ser mayor a las libras al ingreso');
        }

        $hasProcessedLbs = $trimmedLbs !== null || $overripeLbs !== null;

        if ($appliedRawLbs !== null && $hasProcessedLbs && (float) $trimmedLbs + (float) $overripeLbs > (float) $appliedRawLbs) {
            throw new BadRequestError('Las libras recortadas y sobremaduras no pueden superar la MP aplicada');
        }
    }

    /**
     * Resolve the calculated fields with the current configuration of the line.
     *
     * @param  Collection<int, LineField>  $lineFields
     * @param  array<string, mixed>  $columns
     * @return array{recovery_pct: ?float, overripe_pct: ?float, grn_balance: ?float}
     */
    private function calculate(Collection $lineFields, array $columns): array
    {
        $assignedKeys = $lineFields->map(fn (LineField $lineField) => $lineField->captureField->key)->all();

        return $this->lotCalculator->calculate($columns, $assignedKeys);
    }
}
