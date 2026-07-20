<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['sku_id', 'line_id', 'lbs_performance', 'accepted_percentage', 'payment_method', 'status'])]
class LineSku extends Model
{
    protected $table = 'line_stock_keeping_units';

    public function sku()
    {
        return $this->belongsTo(Sku::class);
    }

    public function line()
    {
        return $this->belongsTo(Line::class);
    }
}
