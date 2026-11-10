<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['weekly_plan_task_id', 'user_id', 'raw_material', 'raw_lbs', 'classified_lbs', 'trimmed_lbs', 'rejected_lbs', 'packed_lbs', 'recovery_pct', 'rejection_pct', 'observations', 'extra_values'])]
class WeeklyPlanTaskProductRecord extends Model
{
    /**
     * Capture field keys stored in their own column; any other key goes to extra_values.
     */
    public const SYSTEM_KEYS = [
        'raw_material',
        'raw_lbs',
        'classified_lbs',
        'trimmed_lbs',
        'rejected_lbs',
        'packed_lbs',
        'recovery_pct',
        'rejection_pct',
        'observations',
    ];

    protected $casts = [
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
