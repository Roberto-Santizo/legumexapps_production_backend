<?php

namespace App\Calculators;

class ProductCalculator
{
    /**
     * Resolve the calculated fields of a product record; a field is only calculated when it is assigned to the line
     * and all of its inputs are present, otherwise it stays null. Percentages are stored in a 0-100 scale.
     *
     * @param  array<string, mixed>  $values  captured system values of the record
     * @param  array<int, string>  $assignedKeys  keys assigned to the line
     * @return array{recovery_pct: ?float, rejection_pct: ?float}
     */
    public function calculate(array $values, array $assignedKeys): array
    {
        $rawLbs = $values['raw_lbs'] ?? null;
        $trimmedLbs = $values['trimmed_lbs'] ?? null;
        $rejectedLbs = $values['rejected_lbs'] ?? null;

        $hasRawLbs = $rawLbs !== null && (float) $rawLbs > 0;

        $recoveryPct = in_array('recovery_pct', $assignedKeys, true) && $trimmedLbs !== null && $hasRawLbs
            ? (float) $trimmedLbs / (float) $rawLbs * 100
            : null;

        $rejectionPct = in_array('rejection_pct', $assignedKeys, true) && $rejectedLbs !== null && $hasRawLbs
            ? (float) $rejectedLbs / (float) $rawLbs * 100
            : null;

        return [
            'recovery_pct' => $recoveryPct,
            'rejection_pct' => $rejectionPct,
        ];
    }
}
