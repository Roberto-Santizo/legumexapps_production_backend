<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['weekly_plan_task_id', 'weekly_plan_employee_id', 'position_id', 'replaced_weekly_plan_employee_id'])]
class WeeklyPlanTaskEmployee extends Model
{
    use SoftDeletes;

    public function task()
    {
        return $this->belongsTo(WeeklyPlanTask::class, 'weekly_plan_task_id');
    }

    public function weeklyPlanEmployee()
    {
        return $this->belongsTo(WeeklyPlanEmployee::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function replacedWeeklyPlanEmployee()
    {
        return $this->belongsTo(WeeklyPlanEmployee::class, 'replaced_weekly_plan_employee_id');
    }
}
