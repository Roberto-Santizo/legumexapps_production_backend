<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['percentage', 'stock_keeping_unit_id', 'raw_material_id'])]
class SkuRawMaterial extends Model
{
    public function sku()
    {
        return $this->belongsTo(Sku::class, 'stock_keeping_unit_id');
    }

    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class);
    }
}
