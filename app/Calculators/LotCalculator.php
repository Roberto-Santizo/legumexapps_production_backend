<?php

namespace App\Calculators;

class LotCalculator
{
    /**
     * Resolve the calculated fields of a lot record; a field is only calculated when it is assigned to the line
     * and all of its inputs are present, otherwise it stays null. Percentages are stored in a 0-100 scale.
     *
     * @param  array<string, mixed>  $values  captured system values of the record
     * @param  array<int, string>  $assignedKeys  keys assigned to the line
     * @return array{recovery_pct: ?float, overripe_pct: ?float, grn_balance: ?float}
     */
    public function calculate(array $values, array $assignedKeys): array
    {
        $intakeLbs = $values['intake_lbs'] ?? null;
        $appliedRawLbs = $values['applied_raw_lbs'] ?? null;
        $trimmedLbs = $values['trimmed_lbs'] ?? null;
        $overripeLbs = $values['overripe_lbs'] ?? null;

        $hasAppliedRawLbs = $appliedRawLbs !== null && (float) $appliedRawLbs > 0;

        $recoveryPct = in_array('recovery_pct', $assignedKeys, true) && $trimmedLbs !== null && $hasAppliedRawLbs
            ? (float) $trimmedLbs / (float) $appliedRawLbs * 100
            : null;

        $overripePct = in_array('overripe_pct', $assignedKeys, true) && $overripeLbs !== null && $hasAppliedRawLbs
            ? (float) $overripeLbs / (float) $appliedRawLbs * 100
            : null;

        $grnBalance = in_array('grn_balance', $assignedKeys, true) && $intakeLbs !== null && $appliedRawLbs !== null
            ? (float) $intakeLbs - (float) $appliedRawLbs
            : null;

        return [
            'recovery_pct' => $recoveryPct,
            'overripe_pct' => $overripePct,
            'grn_balance' => $grnBalance,
        ];
    }
}
