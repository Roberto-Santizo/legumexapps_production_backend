<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'unit'])]
class Activity extends Model
{
    public function lineSkuActivities()
    {
        return $this->hasMany(LineSkuActivity::class);
    }

    public function positions()
    {
        return $this->hasMany(Position::class);
    }
}
