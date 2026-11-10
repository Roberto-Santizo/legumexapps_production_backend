<?php

namespace App\Calculators;

use App\Enums\CaptureType;
use App\Models\WeeklyPlanTask;
use App\Models\WeeklyPlanTaskLotRecord;
use App\Models\WeeklyPlanTaskPerformanceRecord;
use App\Models\WeeklyPlanTaskProductRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CaptureSummaryCalculator
{
    /**
     * Label used for groups whose key is null.
     */
    public const EMPTY_GROUP_LABEL = '(sin valor)';

    /**
     * Summarize pallet records: column totals, weighted difference percentage and groups by record status.
     *
     * @param  Collection<int, WeeklyPlanTaskPerformanceRecord>  $records
     * @param  Collection<int, WeeklyPlanTask>  $tasks  tasks in scope, with performance.sku and performance.line
     * @return array<string, mixed>
     */
    public function pallet(Collection $records, Collection $tasks): array
    {
        return $this->summarize(
            captureType: CaptureType::Pallet,
            records: $records,
            tasks: $tasks,
            totalColumns: ['boxes', 'liters', 'scale_weight', 'tare', 'net_weight', 'ticket_weight', 'difference'],
            producedColumn: 'net_weight',
            percentages: ['difference_pct' => ['difference', 'ticket_weight']],
            groupKey: 'status',
            groupColumns: ['net_weight'],
            groupPercentages: [],
        );
    }

    /**
     * Summarize lot records: column totals, weighted recovery and overripe percentages and groups by lot.
     *
     * @param  Collection<int, WeeklyPlanTaskLotRecord>  $records
     * @param  Collection<int, WeeklyPlanTask>  $tasks  tasks in scope, with performance.sku and performance.line
     * @return array<string, mixed>
     */
    public function lot(Collection $records, Collection $tasks): array
    {
        return $this->summarize(
            captureType: CaptureType::Lot,
            records: $records,
            tasks: $tasks,
            totalColumns: ['intake_lbs', 'applied_raw_lbs', 'trimmed_lbs', 'overripe_lbs', 'grn_balance'],
            producedColumn: 'trimmed_lbs',
            percentages: [
                'recovery_pct' => ['trimmed_lbs', 'applied_raw_lbs'],
                'overripe_pct' => ['overripe_lbs', 'applied_raw_lbs'],
            ],
            groupKey: 'lot',
            groupColumns: ['applied_raw_lbs', 'trimmed_lbs'],
            groupPercentages: ['recovery_pct' => ['trimmed_lbs', 'applied_raw_lbs']],
        );
    }

    /**
     * Summarize product records: column totals, weighted recovery and rejection percentages and groups by raw material.
     *
     * @param  Collection<int, WeeklyPlanTaskProductRecord>  $records
     * @param  Collection<int, WeeklyPlanTask>  $tasks  tasks in scope, with performance.sku and performance.line
     * @return array<string, mixed>
     */
    public function product(Collection $records, Collection $tasks): array
    {
        $percentages = [
            'recovery_pct' => ['trimmed_lbs', 'raw_lbs'],
            'rejection_pct' => ['rejected_lbs', 'raw_lbs'],
        ];

        return $this->summarize(
            captureType: CaptureType::Product,
            records: $records,
            tasks: $tasks,
            totalColumns: ['raw_lbs', 'classified_lbs', 'trimmed_lbs', 'rejected_lbs', 'packed_lbs'],
            producedColumn: 'trimmed_lbs',
            percentages: $percentages,
            groupKey: 'raw_material',
            groupColumns: ['raw_lbs', 'trimmed_lbs', 'rejected_lbs'],
            groupPercentages: $percentages,
        );
    }

    /**
     * Build the summary shared by the three capture families.
     *
     * @param  Collection<int, Model>  $records
     * @param  Collection<int, WeeklyPlanTask>  $tasks
     * @param  array<int, string>  $totalColumns
     * @param  array<string, array{0: string, 1: string}>  $percentages  name => [numerator column, denominator column]
     * @param  array<int, string>  $groupColumns
     * @param  array<string, array{0: string, 1: string}>  $groupPercentages
     * @return array{capture_type: string, tasks_count: int, records_count: int, planned_pounds: ?float, produced_lbs: ?float, progress_pct: ?float, totals: array<string, ?float>, groups: array<int, array<string, mixed>>, by_task: array<int, array<string, mixed>>}
     */
    private function summarize(
        CaptureType $captureType,
        Collection $records,
        Collection $tasks,
        array $totalColumns,
        string $producedColumn,
        array $percentages,
        string $groupKey,
        array $groupColumns,
        array $groupPercentages,
    ): array {
        $plannedPounds = $tasks->isEmpty() ? null : (float) $tasks->sum(fn (WeeklyPlanTask $task) => $this->plannedPounds($task));
        $producedLbs = $this->sumColumn($records, $producedColumn);

        $totals = [];

        foreach ($totalColumns as $column) {
            $totals[$column] = $this->sumColumn($records, $column);
        }

        foreach ($percentages as $name => [$numerator, $denominator]) {
            $totals[$name] = $this->weightedPercentage($records, $numerator, $denominator);
        }

        return [
            'capture_type' => $captureType->value,
            'tasks_count' => $tasks->count(),
            'records_count' => $records->count(),
            'planned_pounds' => $plannedPounds,
            'produced_lbs' => $producedLbs,
            'progress_pct' => $this->progressPercentage($producedLbs, $plannedPounds),
            'totals' => $totals,
            'groups' => $this->groups($records, $groupKey, $groupColumns, $groupPercentages),
            'by_task' => $this->byTask($records, $tasks, $producedColumn, $percentages),
        ];
    }

    /**
     * Group the records by the given key and summarize each group.
     *
     * @param  Collection<int, Model>  $records
     * @param  array<int, string>  $columns
     * @param  array<string, array{0: string, 1: string}>  $percentages
     * @return array<int, array<string, mixed>>
     */
    private function groups(Collection $records, string $groupKey, array $columns, array $percentages): array
    {
        return $records
            ->groupBy(fn (Model $record) => $record->{$groupKey} ?? self::EMPTY_GROUP_LABEL)
            ->map(function (Collection $groupRecords, string|int $key) use ($groupKey, $columns, $percentages) {
                $group = [
                    $groupKey => $key,
                    'records_count' => $groupRecords->count(),
                ];

                foreach ($columns as $column) {
                    $group[$column] = $this->sumColumn($groupRecords, $column);
                }

                foreach ($percentages as $name => [$numerator, $denominator]) {
                    $group[$name] = $this->weightedPercentage($groupRecords, $numerator, $denominator);
                }

                return $group;
            })
            ->values()
            ->all();
    }

    /**
     * Summarize the records of each task in scope.
     *
     * @param  Collection<int, Model>  $records
     * @param  Collection<int, WeeklyPlanTask>  $tasks
     * @param  array<string, array{0: string, 1: string}>  $percentages
     * @return array<int, array<string, mixed>>
     */
    private function byTask(Collection $records, Collection $tasks, string $producedColumn, array $percentages): array
    {
        $recordsByTask = $records->groupBy('weekly_plan_task_id');

        return $tasks
            ->map(function (WeeklyPlanTask $task) use ($recordsByTask, $producedColumn, $percentages) {
                $taskRecords = $recordsByTask->get($task->id, collect());
                $producedLbs = $this->sumColumn($taskRecords, $producedColumn);

                $row = [
                    'task_id' => $task->id,
                    'line_code' => $task->performance?->line?->code,
                    'sku_code' => $task->performance?->sku?->code,
                    'operation_date' => $task->operation_date?->format('Y-m-d'),
                    'status' => $task->status,
                    'records_count' => $taskRecords->count(),
                    'produced_lbs' => $producedLbs,
                    'progress_pct' => $this->progressPercentage($producedLbs, $this->plannedPounds($task)),
                ];

                foreach ($percentages as $name => [$numerator, $denominator]) {
                    $row[$name] = $this->weightedPercentage($taskRecords, $numerator, $denominator);
                }

                return $row;
            })
            ->values()
            ->all();
    }

    /**
     * Sum a column ignoring nulls; null when every value is null.
     *
     * @param  Collection<int, Model>  $records
     */
    private function sumColumn(Collection $records, string $column): ?float
    {
        $values = $records->pluck($column)->reject(fn (mixed $value) => $value === null);

        if ($values->isEmpty()) {
            return null;
        }

        return (float) $values->sum(fn (mixed $value) => (float) $value);
    }

    /**
     * Weighted percentage Σ numerator / Σ denominator × 100 over the rows where both values are present.
     *
     * @param  Collection<int, Model>  $records
     */
    private function weightedPercentage(Collection $records, string $numerator, string $denominator): ?float
    {
        $rows = $records->filter(fn (Model $record) => $record->{$numerator} !== null && $record->{$denominator} !== null);

        $denominatorSum = (float) $rows->sum(fn (Model $record) => (float) $record->{$denominator});

        if ($denominatorSum == 0.0) {
            return null;
        }

        $numeratorSum = (float) $rows->sum(fn (Model $record) => (float) $record->{$numerator});

        return $numeratorSum / $denominatorSum * 100;
    }

    /**
     * Planned pounds of a task: boxes × SKU presentation.
     */
    private function plannedPounds(WeeklyPlanTask $task): float
    {
        return (float) $task->boxes * (float) ($task->performance?->sku?->presentation ?? 0);
    }

    /**
     * Progress percentage of produced over planned pounds; null when nothing was planned.
     */
    private function progressPercentage(?float $producedLbs, ?float $plannedPounds): ?float
    {
        if (! $plannedPounds) {
            return null;
        }

        return (float) ($producedLbs ?? 0) / $plannedPounds * 100;
    }
}
