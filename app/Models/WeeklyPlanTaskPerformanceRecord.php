<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['weekly_plan_task_id', 'user_id', 'pallet_number', 'lot', 'recorded_at', 'boxes', 'liters', 'status', 'scale_weight', 'tare', 'net_weight', 'ticket_weight', 'difference', 'observations', 'extra_values'])]
class WeeklyPlanTaskPerformanceRecord extends Model
{
    /**
     * Capture field keys stored in their own column; any other key goes to extra_values.
     */
    public const SYSTEM_KEYS = [
        'pallet_number',
        'lot',
        'recorded_at',
        'boxes',
        'liters',
        'status',
        'scale_weight',
        'tare',
        'net_weight',
        'ticket_weight',
        'difference',
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
