<?php

namespace App\Calculators;

class PalletCalculator
{
    /**
     * Resolve the calculated fields of a pallet record; a field is only calculated when it is assigned to the line
     * and all of its inputs are present, otherwise it stays null.
     *
     * @param  array<string, mixed>  $values  captured system values of the record
     * @param  array<int, string>  $assignedKeys  keys assigned to the line
     * @return array{net_weight: ?float, ticket_weight: ?float, difference: ?float}
     */
    public function calculate(array $values, ?float $presentation, array $assignedKeys): array
    {
        $scaleWeight = $values['scale_weight'] ?? null;
        $tare = $values['tare'] ?? null;
        $boxes = $values['boxes'] ?? null;

        $netWeight = in_array('net_weight', $assignedKeys, true) && $scaleWeight !== null && $tare !== null
            ? (float) $scaleWeight - (float) $tare
            : null;

        $ticketWeight = in_array('ticket_weight', $assignedKeys, true) && $boxes !== null && $presentation > 0
            ? (float) $boxes * $presentation
            : null;

        $difference = in_array('difference', $assignedKeys, true) && $netWeight !== null && $ticketWeight !== null
            ? $netWeight - $ticketWeight
            : null;

        return [
            'net_weight' => $netWeight,
            'ticket_weight' => $ticketWeight,
            'difference' => $difference,
        ];
    }
}
