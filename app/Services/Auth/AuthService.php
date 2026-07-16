<?php

namespace App\Services\Auth;

use App\Errors\BadRequestError;
use App\Interfaces\Auth\AuthServiceInterface;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService implements AuthServiceInterface
{
    public function login(array $data)
    {
        $token = JWTAuth::attempt($data);

        if (! $token) {
            throw new BadRequestError('Credenciales inválidas');
        }

        $user = auth()->user();
        $permissions = auth()->user()->permissions;

        $formattedPermissions = $permissions->map(function ($permission) {
            return $permission->permission->name;
        });

        return [
            'name' => $user->name,
            'role' => $user->role ?? 'admin',
            'permissions' => $formattedPermissions,
            'token' => $token,
        ];
    }
}
