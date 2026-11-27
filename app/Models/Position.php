<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'activity_id', 'line_sku_id', 'status'])]
class Position extends Model
{
    use HasFactory;

    public function lineSku()
    {
        return $this->belongsTo(LineSku::class, 'line_sku_id');
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }
}
