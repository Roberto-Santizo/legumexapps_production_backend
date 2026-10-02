<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['weekly_plan_task_id', 'user_id', 'pallet_number', 'boxes', 'weighed_pounds', 'theoretical_pounds', 'difference_pounds'])]
class WeeklyPlanTaskPerformanceRecord extends Model
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
