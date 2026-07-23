<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['position_id', 'employee_id', 'weekly_plan_id'])]
class WeeklyPlanEmployee extends Model
{
    use HasFactory;

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function weeklyPlan()
    {
        return $this->belongsTo(WeeklyPlan::class);
    }
}
