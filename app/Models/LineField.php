<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['line_id', 'capture_field_id', 'is_required', 'order', 'label'])]
class LineField extends Model
{
    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function line()
    {
        return $this->belongsTo(Line::class);
    }

    public function captureField()
    {
        return $this->belongsTo(CaptureField::class);
    }
}
