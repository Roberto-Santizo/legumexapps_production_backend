<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

#[Fillable(['name', 'email', 'password', 'role', 'username'])]
#[Hidden(['password'])]

class User extends Authenticatable implements JWTSubject
{
    use HasFactory;

    public function permissions()
    {
        return $this->hasMany(UserPermission::class);
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'role' => $this->role,
        ];
    }
}
