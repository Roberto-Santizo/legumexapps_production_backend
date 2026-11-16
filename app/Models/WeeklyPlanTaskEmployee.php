<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['weekly_plan_task_id', 'code', 'name', 'position_id'])]
class WeeklyPlanTaskEmployee extends Model
{
    use SoftDeletes;

    public function task()
    {
        return $this->belongsTo(WeeklyPlanTask::class, 'weekly_plan_task_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }
}
