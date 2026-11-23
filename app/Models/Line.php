<?php

namespace App\Models;

use App\Enums\CaptureType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'code', 'shift', 'capture_type', 'is_wildcard', 'supervisor_code'])]
class Line extends Model
{
    protected $casts = [
        'capture_type' => CaptureType::class,
        'is_wildcard' => 'boolean',
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

    public function lineFields()
    {
        return $this->hasMany(LineField::class);
    }

    public function lineActivities()
    {
        return $this->hasMany(LineActivity::class);
    }
}
