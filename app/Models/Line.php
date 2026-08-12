<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'code', 'shift'])]
class Line extends Model
{

    public function performances()
    {
        return $this->hasMany(LineSku::class, 'line_id', 'id');
    }

    public function dependencies()
    {
        return $this->hasMany(LineDependency::class, 'line_id', 'id');
    }
}
