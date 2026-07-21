<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['lbs_per_item', 'sku_id', 'packing_material_id'])]
class SkuPackingMaterial extends Model
{
    public function sku()
    {
        return $this->belongsTo(Sku::class, 'sku_id', 'id');
    }

    public function packingMaterial()
    {
        return $this->belongsTo(PackingMaterial::class);
    }
}
