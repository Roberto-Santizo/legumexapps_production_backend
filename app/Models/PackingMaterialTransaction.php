<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['reference', 'responsable', 'observations', 'responsable_signature', 'user_signature', 'type', 'user_id', 'weekly_plan_task_id'])]
class PackingMaterialTransaction extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function task()
    {
        return $this->belongsTo(WeeklyPlanTask::class);
    }

    public function items()
    {
        return $this->hasMany(PackingMaterialTransactionItem::class, 'pm_transaction_id');
    }
}
