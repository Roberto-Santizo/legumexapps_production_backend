<?php

namespace App\Services\WeeklyPlanTaskPerformanceRecords;

use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Interfaces\WeeklyPlanTaskPerformanceRecords\WeeklyPlanTaskPerformanceRecordsServiceInterface;
use App\Interfaces\WeeklyPlanTasks\WeeklyPlanTasksServiceInterface;
use App\Models\WeeklyPlanTask;
use App\Models\WeeklyPlanTaskPerformanceRecord;
use Illuminate\Http\Request;
use Override;

class WeeklyPlanTaskPerformanceRecordsService implements WeeklyPlanTaskPerformanceRecordsServiceInterface
{
    public function __construct(private WeeklyPlanTasksServiceInterface $weeklyPlanTasksService) {}

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

        $palletNumber = $data['pallet_number'] ?? null;
        $boxes = $data['boxes'] ?? null;
        $this->ensurePalletNumberIsAvailable($task, $palletNumber);

        return WeeklyPlanTaskPerformanceRecord::create([
            'weekly_plan_task_id' => $task->id,
            'user_id' => auth()->user()->id,
            'pallet_number' => $palletNumber,
            'boxes' => $boxes,
            'weighed_pounds' => $data['weighed_pounds'],
            ...$this->calculatePounds($task, $boxes, (float) $data['weighed_pounds']),
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

        $palletNumber = array_key_exists('pallet_number', $data) ? $data['pallet_number'] : $record->pallet_number;
        $boxes = array_key_exists('boxes', $data) ? $data['boxes'] : $record->boxes;
        $weighedPounds = (float) ($data['weighed_pounds'] ?? $record->weighed_pounds);
        $this->ensurePalletNumberIsAvailable($task, $palletNumber, $record->id);

        $record->update([
            'pallet_number' => $palletNumber,
            'boxes' => $boxes,
            'weighed_pounds' => $weighedPounds,
            ...$this->calculatePounds($task, $boxes, $weighedPounds),
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
     * Theoretical pounds are the boxes times the SKU presentation; both values are 0 when either is missing.
     *
     * @return array{theoretical_pounds: float, difference_pounds: float}
     */
    private function calculatePounds(WeeklyPlanTask $task, ?int $boxes, float $weighedPounds): array
    {
        $presentation = $task->performance?->sku?->presentation;

        if (! $boxes || ! $presentation) {
            return ['theoretical_pounds' => 0, 'difference_pounds' => 0];
        }

        $theoreticalPounds = $boxes * $presentation;

        return [
            'theoretical_pounds' => $theoreticalPounds,
            'difference_pounds' => $weighedPounds - $theoreticalPounds,
        ];
    }
}
