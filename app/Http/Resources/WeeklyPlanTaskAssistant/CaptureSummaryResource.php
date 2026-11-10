<?php

namespace App\Http\Resources\WeeklyPlanTaskAssistant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaptureSummaryResource extends JsonResource
{
    /**
     * Transform the calculator summary into an array, rounding every number to 2 decimals.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->roundNumbers($this->resource);
    }

    /**
     * Round every float of the given value recursively.
     */
    private function roundNumbers(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item) => $this->roundNumbers($item), $value);
        }

        if (is_float($value)) {
            return round($value, 2);
        }

        return $value;
    }
}
