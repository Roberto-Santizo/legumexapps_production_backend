<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['boxes', 'produced_boxes', 'pallets', 'produced_pallets', 'hours', 'weighed_pounds', 'destination', 'operation_date', 'start_date', 'end_date', 'weekly_plan_id', 'line_sku_id'])]
class WeeklyPlanTask extends Model
{
    protected $casts = [
        'operation_date' => 'datetime'
    ];
    public function performance()
    {
        return $this->belongsTo(LineSku::class, 'line_sku_id', 'id');
    }

    public function weeklyPlan()
    {
        return $this->belongsTo(WeeklyPlan::class);
    }
}
