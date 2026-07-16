<?php

namespace App\Http\Controllers\Users;

use App\Helpers\ResponseHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\AddPermissionToUserRequest;
use App\Interfaces\Users\UserPermissionServiceInterface;
use Illuminate\Http\Request;

class UserPermissionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, UserPermissionServiceInterface $service)
    {
        try {
            $id = $request->query('userId');
            $result = $service->getUserPermissions($id);

            return ResponseHandler::success($result, 'Permisos Obtenidos Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AddPermissionToUserRequest $request, UserPermissionServiceInterface $service)
    {
        try {
            $data = $request->validated();
            $result = $service->addPermissionToUser($data);

            return ResponseHandler::success($result, 'Permiso Agregado Correctamente', 201);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, UserPermissionServiceInterface $service)
    {
        try {
            $result = $service->getUserPermissionById($id);

            return ResponseHandler::success($result, 'Permiso Obtenido Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, UserPermissionServiceInterface $service)
    {
        try {
            $result = $service->deleteUserPermissionById($id);

            return ResponseHandler::success($result, 'Permiso Eliminado Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
