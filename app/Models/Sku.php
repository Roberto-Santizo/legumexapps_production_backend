<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'product_name', 'presentation', 'boxes_per_pallet', 'client_id'])]
class Sku extends Model
{
    protected $table = 'stock_keeping_units';

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function packingMaterialItems()
    {
        return $this->hasMany(SkuPackingMaterial::class, 'sku_id', 'id');
    }

    public function rawMaterialItems()
    {
        return $this->hasMany(SkuRawMaterial::class, 'stock_keeping_unit_id', 'id');
    }
}
