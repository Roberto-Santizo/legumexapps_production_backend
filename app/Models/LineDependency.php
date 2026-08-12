<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['line_id', 'line_dependent_id'])]
class LineDependency extends Model
{
    public function baseLine()
    {
        return $this->belongsTo(Line::class);
    }

    public function dependentLine()
    {
        return $this->hasOne(Line::class, 'line_dependent_id', 'id');
    }
}
