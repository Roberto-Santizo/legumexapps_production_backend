<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['quantity', 'lote', 'destination', 'packing_material_id', 'pm_transaction_id'])]
class PackingMaterialTransactionItem extends Model
{
    public function packingMaterial()
    {
        return $this->belongsTo(PackingMaterial::class);
    }

    public function packingMaterialTransaction()
    {
        return $this->belongsTo(PackingMaterialTransaction::class, 'pm_transaction_id');
    }
}
