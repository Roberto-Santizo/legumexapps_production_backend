<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['weekly_plan_task_id', 'user_id', 'entry_date', 'lot', 'recorded_at', 'intake_lbs', 'applied_raw_lbs', 'trimmed_lbs', 'overripe_lbs', 'recovery_pct', 'overripe_pct', 'grn_balance', 'observations', 'extra_values'])]
class WeeklyPlanTaskLotRecord extends Model
{
    /**
     * Capture field keys stored in their own column; any other key goes to extra_values.
     */
    public const SYSTEM_KEYS = [
        'entry_date',
        'lot',
        'recorded_at',
        'intake_lbs',
        'applied_raw_lbs',
        'trimmed_lbs',
        'overripe_lbs',
        'recovery_pct',
        'overripe_pct',
        'grn_balance',
        'observations',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'extra_values' => 'array',
    ];

    public function task()
    {
        return $this->belongsTo(WeeklyPlanTask::class, 'weekly_plan_task_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
