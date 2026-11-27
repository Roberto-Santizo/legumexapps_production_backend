<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['line_sku_id', 'activity_id', 'quantity'])]
class LineSkuActivity extends Model
{
    protected $casts = [
        'quantity' => 'integer',
    ];

    public function lineSku()
    {
        return $this->belongsTo(LineSku::class, 'line_sku_id');
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }

    public function taskEmployees()
    {
        return $this->hasMany(WeeklyPlanTaskEmployee::class);
    }
}
