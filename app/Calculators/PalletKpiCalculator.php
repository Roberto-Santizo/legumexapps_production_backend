<?php

namespace App\Calculators;

use App\Models\WeeklyPlanTask;
use App\Models\WeeklyPlanTaskPerformanceRecord;
use Illuminate\Support\Collection;

class PalletKpiCalculator
{
    /**
     * Produced pallets come from boxes / boxes per pallet.
     */
    public const PALLETS_FROM_FORMULA = 'formula';

    /**
     * Produced pallets come from the distinct pallet numbers, when the formula cannot be applied.
     */
    public const PALLETS_FROM_COUNT = 'count';

    /**
     * Build the pallet KPI report: one row per task with records, plus the totals when there are two or more rows.
     * Values keep full precision; dates and rounding are formatted by the Resource.
     *
     * @param  Collection<int, WeeklyPlanTaskPerformanceRecord>  $records
     * @param  Collection<int, WeeklyPlanTask>  $tasks  tasks in scope, with performance.sku.client and performance.line
     * @return array{tasks_count: int, tasks_without_records: int, rows: array<int, array<string, mixed>>, totals: ?array<string, ?float>}
     */
    public function report(Collection $records, Collection $tasks): array
    {
        $recordsByTask = $records->groupBy('weekly_plan_task_id');

        $rows = $tasks
            ->filter(fn (WeeklyPlanTask $task) => $recordsByTask->has($task->id))
            ->map(fn (WeeklyPlanTask $task) => $this->row($task, $recordsByTask->get($task->id)))
            ->sortBy(fn (array $row) => [
                $row['operation_date'] === null,
                $row['operation_date']?->format('Y-m-d'),
                $row['line_name'],
                $row['task_id'],
            ])
            ->values();

        return [
            'tasks_count' => $rows->count(),
            'tasks_without_records' => $tasks->count() - $rows->count(),
            'rows' => $rows->all(),
            'totals' => $rows->count() >= 2 ? $this->totals($rows) : null,
        ];
    }

    /**
     * Calculate the KPI row of a task from its records, its waste and its SKU.
     *
     * @param  Collection<int, WeeklyPlanTaskPerformanceRecord>  $records
     * @return array<string, mixed>
     */
    private function row(WeeklyPlanTask $task, Collection $records): array
    {
        $sku = $task->performance?->sku;

        $lbsPerBox = $this->toFloat($sku?->presentation);
        $boxesPerPallet = $this->toFloat($sku?->boxes_per_pallet);
        $producedBoxes = $this->sum($records->pluck('boxes'));
        $producedLbs = $producedBoxes === null || $lbsPerBox === null ? null : $producedBoxes * $lbsPerBox;
        $wasteLbs = $this->toFloat($task->waste_pounds);
        $overdoseLbs = $this->sum($records->pluck('difference'));
        [$producedPallets, $palletsSource] = $this->producedPallets($records, $producedBoxes, $boxesPerPallet);

        return [
            'task_id' => $task->id,
            'status' => $task->status,
            'operation_date' => $task->operation_date,
            'line_name' => $task->performance?->line?->name,
            'sku_code' => $sku?->code,
            'sku_name' => $sku?->product_name,
            'client_name' => $sku?->client?->name,
            'lbs_per_box' => $lbsPerBox,
            'boxes_per_pallet' => $boxesPerPallet,
            'produced_pallets' => $producedPallets,
            'produced_boxes' => $producedBoxes,
            'produced_lbs' => $producedLbs,
            'waste_lbs' => $wasteLbs,
            'waste_pct' => $this->percentage($wasteLbs, $producedLbs),
            'overdose_lbs' => $overdoseLbs,
            'overdose_pct' => $this->percentage($overdoseLbs, $producedLbs),
            'overdose_lbs_per_pallet' => $this->ratio($overdoseLbs, $producedPallets),
            'pallets_source' => $palletsSource,
        ];
    }

    /**
     * Produced pallets and their source: boxes / boxes per pallet, or the distinct pallet numbers as fallback.
     *
     * @param  Collection<int, WeeklyPlanTaskPerformanceRecord>  $records
     * @return array{0: ?float, 1: ?string}
     */
    private function producedPallets(Collection $records, ?float $producedBoxes, ?float $boxesPerPallet): array
    {
        $palletsByFormula = $this->ratio($producedBoxes, $boxesPerPallet);

        if ($palletsByFormula !== null) {
            return [$palletsByFormula, self::PALLETS_FROM_FORMULA];
        }

        $palletsByCount = $records->pluck('pallet_number')->whereNotNull()->unique()->count();

        return $palletsByCount === 0 ? [null, null] : [(float) $palletsByCount, self::PALLETS_FROM_COUNT];
    }

    /**
     * Column sums and weighted indicators of the rows.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, ?float>
     */
    private function totals(Collection $rows): array
    {
        return [
            'produced_pallets' => $this->sum($rows->pluck('produced_pallets')),
            'produced_boxes' => $this->sum($rows->pluck('produced_boxes')),
            'produced_lbs' => $this->sum($rows->pluck('produced_lbs')),
            'waste_lbs' => $this->sum($rows->pluck('waste_lbs')),
            'waste_pct' => $this->percentage(...$this->pairedSums($rows, 'waste_lbs', 'produced_lbs')),
            'overdose_lbs' => $this->sum($rows->pluck('overdose_lbs')),
            'overdose_pct' => $this->percentage(...$this->pairedSums($rows, 'overdose_lbs', 'produced_lbs')),
            'overdose_lbs_per_pallet' => $this->ratio(...$this->pairedSums($rows, 'overdose_lbs', 'produced_pallets')),
        ];
    }

    /**
     * Sums of two columns over the rows where both values are present.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{0: ?float, 1: ?float}
     */
    private function pairedSums(Collection $rows, string $numerator, string $denominator): array
    {
        $pairedRows = $rows->whereNotNull($numerator)->whereNotNull($denominator);

        return [$this->sum($pairedRows->pluck($numerator)), $this->sum($pairedRows->pluck($denominator))];
    }

    /**
     * Sum ignoring nulls; null when every value is null.
     *
     * @param  Collection<int, mixed>  $values
     */
    private function sum(Collection $values): ?float
    {
        $presentValues = $values->whereNotNull();

        return $presentValues->isEmpty() ? null : (float) $presentValues->sum(fn (mixed $value) => (float) $value);
    }

    /**
     * Numerator / denominator × 100; null when it cannot be calculated.
     */
    private function percentage(?float $numerator, ?float $denominator): ?float
    {
        $ratio = $this->ratio($numerator, $denominator);

        return $ratio === null ? null : $ratio * 100;
    }

    /**
     * Numerator / denominator; null when either is null or the denominator is 0.
     */
    private function ratio(?float $numerator, ?float $denominator): ?float
    {
        if ($numerator === null || $denominator === null || $denominator === 0.0) {
            return null;
        }

        return $numerator / $denominator;
    }

    private function toFloat(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
