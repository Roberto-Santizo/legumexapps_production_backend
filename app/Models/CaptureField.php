<?php

namespace App\Models;

use App\Enums\CaptureFieldDataType;
use App\Enums\CaptureType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'label', 'data_type', 'capture_type', 'is_system', 'is_calculated', 'depends_on', 'options'])]
class CaptureField extends Model
{
    protected $casts = [
        'data_type' => CaptureFieldDataType::class,
        'capture_type' => CaptureType::class,
        'is_system' => 'boolean',
        'is_calculated' => 'boolean',
        'depends_on' => 'array',
        'options' => 'array',
    ];

    public function lineFields()
    {
        return $this->hasMany(LineField::class);
    }
}
