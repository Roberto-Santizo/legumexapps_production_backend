<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHandler;
use App\Http\Requests\Auth\LoginRequest;
use App\Interfaces\Auth\AuthServiceInterface;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(LoginRequest $request, AuthServiceInterface $authService)
    {
        try {
            $data = $request->validated();
            $user = $authService->login($data);

            return ResponseHandler::success($user, 'Sesión Iniciada Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    public function checkstatus()
    {
        try {

            $user = auth()->user();
            $token = JWTAuth::fromUser($user);

            $permissions = $user->permissions;

            $formattedPermissions = $permissions->map(function ($permission) {
                return $permission->permission->name;
            });

            $data = ['name' => $user->name, 'role' => $user->role ?? 'admin', 'token' => $token, 'permissions' => $formattedPermissions];

            return ResponseHandler::success($data, 'Usuario Obtenido Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
