<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name'])]
class Timeout extends Model
{
    public function taskTimeouts()
    {
        return $this->hasMany(WeeklyPlanTaskTimeout::class);
    }
}
