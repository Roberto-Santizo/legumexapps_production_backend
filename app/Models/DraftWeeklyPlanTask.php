<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['boxes', 'destination', 'hours', 'operation_date', 'draft_weekly_plan_id', 'sku_id', 'line_id'])]
class DraftWeeklyPlanTask extends Model
{
    protected $casts = [
        'operation_date' => 'datetime',
    ];

    public function sku()
    {
        return $this->belongsTo(Sku::class);
    }

    public function line()
    {
        return $this->belongsTo(Line::class);
    }

    public function DraftWeeklyPlan()
    {
        return $this->belongsTo(DraftWeeklyPlan::class);
    }
}
