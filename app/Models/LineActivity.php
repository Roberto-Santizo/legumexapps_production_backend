<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['line_id', 'activity_id', 'quantity'])]
class LineActivity extends Model
{
    protected $casts = [
        'quantity' => 'integer',
    ];

    public function line()
    {
        return $this->belongsTo(Line::class);
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }
}
