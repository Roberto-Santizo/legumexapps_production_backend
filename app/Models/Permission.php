<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name'])]

class Permission extends Model
{
    public function users()
    {
        return $this->hasMany(UserPermission::class);
    }
}
