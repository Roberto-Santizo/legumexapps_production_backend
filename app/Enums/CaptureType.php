<?php

namespace App\Enums;

enum CaptureType: string
{
    case Pallet = 'pallet';
    case Lot = 'lot';
    case Product = 'product';
}
