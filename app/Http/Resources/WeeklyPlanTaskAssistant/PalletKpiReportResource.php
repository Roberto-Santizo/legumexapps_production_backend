<?php

namespace App\Http\Resources\WeeklyPlanTaskAssistant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PalletKpiReportResource extends JsonResource
{
    /**
     * Transform the calculator report into an array, rounding every number to 2 decimals except lbs_per_box.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'tasks_count' => $this->resource['tasks_count'],
            'tasks_without_records' => $this->resource['tasks_without_records'],
            'rows' => array_map(fn (array $row) => $this->formatRow($row), $this->resource['rows']),
            'totals' => $this->resource['totals'] === null ? null : $this->roundNumbers($this->resource['totals']),
        ];
    }

    /**
     * Presentations such as 22.046 keep every decimal.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function formatRow(array $row): array
    {
        return [
            ...$this->roundNumbers($row),
            'operation_date' => $row['operation_date'] ? $row['operation_date']->format('d-m-Y') : 'SIN PROGRAMACIÓN',
            'lbs_per_box' => $row['lbs_per_box'],
        ];
    }

    /**
     * Round every float of the given values.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function roundNumbers(array $values): array
    {
        return array_map(fn (mixed $value) => is_float($value) ? round($value, 2) : $value, $values);
    }
}
