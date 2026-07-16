<?php

namespace App\Http\Controllers\Users;

use App\Helpers\ResponseHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\CreateUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\Users\PaginatedUsersResource;
use App\Http\Resources\Users\UserPermissionResource;
use App\Http\Resources\Users\UserResource;
use App\Interfaces\Users\UserServiceInterface;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, UserServiceInterface $service)
    {
        try {
            $limit = $request->query('limit');
            $users = $service->getUsers($limit, $request);
            $data = $limit ? new PaginatedUsersResource($users) : UserResource::collection($users);

            return ResponseHandler::success($data, 'Usuarios Obtenidos Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateUserRequest $request, UserServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $service->createUser($data);

            return ResponseHandler::success(null, 'Usuario Creado Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, UserServiceInterface $service)
    {
        try {
            $user = $service->getUserByUsername($id);

            return ResponseHandler::success(new UserResource($user), 'Usuario Obtenido Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, string $id, UserServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $user = $service->updateUserById($data, $id);

            return ResponseHandler::success($user, 'Usuario Actualizado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    public function getUserPermissions(string $username, UserServiceInterface $service)
    {
        try {
            $permissions = $service->getPermissionsByUser($username);

            return ResponseHandler::success(UserPermissionResource::collection($permissions), 'Permisos Obtenidos Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
