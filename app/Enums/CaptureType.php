<?php

namespace App\Enums;

enum CaptureType: string
{
    case Pallet = 'pallet';
    case Lot = 'lot';
    case Product = 'product';

    /**
     * Name of the unit captured by the family, used in user messages.
     */
    public function unitLabel(): string
    {
        return match ($this) {
            self::Pallet => 'tarima',
            self::Lot => 'lote',
            self::Product => 'producto',
        };
    }
}
