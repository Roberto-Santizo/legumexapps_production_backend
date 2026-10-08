<?php

namespace App\Models;

use App\Enums\CaptureType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'code', 'shift', 'capture_type'])]
class Line extends Model
{
    protected $casts = [
        'capture_type' => CaptureType::class,
    ];

    public function performances()
    {
        return $this->hasMany(LineSku::class, 'line_id', 'id');
    }

    public function dependencies()
    {
        return $this->hasMany(LineDependency::class, 'line_id', 'id');
    }

    public function positions()
    {
        return $this->hasMany(Position::class);
    }
}
