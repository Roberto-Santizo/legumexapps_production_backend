<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['weekly_plan_task_id', 'user_id', 'event', 'field', 'old_value', 'new_value'])]
class WeeklyPlanTaskLog extends Model
{
    public function task()
    {
        return $this->belongsTo(WeeklyPlanTask::class, 'weekly_plan_task_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
