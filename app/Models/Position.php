<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'activity', 'line_id', 'status'])]
class Position extends Model
{
    use HasFactory;

    public function line()
    {
        return $this->belongsTo(Line::class);
    }
}
