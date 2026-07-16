<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'permission_id'])]
class UserPermission extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function permission()
    {
        return $this->belongsTo(Permission::class, 'permission_id', 'id');
    }
}
