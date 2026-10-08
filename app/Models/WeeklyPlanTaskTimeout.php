<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['weekly_plan_task_id', 'timeout_id', 'user_id', 'start_date', 'end_date', 'duration_hours', 'observation'])]
class WeeklyPlanTaskTimeout extends Model
{
    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(WeeklyPlanTask::class, 'weekly_plan_task_id');
    }

    public function timeout()
    {
        return $this->belongsTo(Timeout::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
