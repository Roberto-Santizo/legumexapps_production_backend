<?php

namespace App\Services\Users;

use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Interfaces\Users\UserPermissionServiceInterface;
use App\Models\UserPermission;
use Override;

class UserPermissionService implements UserPermissionServiceInterface
{
    #[Override]
    public function addPermissionToUser(array $data)
    {
        $newPermission = UserPermission::create($data);

        return $newPermission;
    }

    #[Override]
    public function getUserPermissions(?string $id)
    {
        if (! $id) {
            throw new BadRequestError('El ID del usuario es requerido');
        }
        $permissions = UserPermission::where('user_id', $id)->get();

        return $permissions;
    }

    #[Override]
    public function getUserPermissionById(string $id)
    {
        $permission = UserPermission::find($id);
        if (! $permission) {
            throw new NotFoundError('El permiso no existe');
        }

        return $permission;
    }

    #[Override]
    public function updateUserPermissionById(array $data, string $id)
    {
        $permission = $this->getUserPermissionById($id);
        $permission->update($data);

        return true;
    }

    #[Override]
    public function deleteUserPermissionById(string $id)
    {
        $permission = $this->getUserPermissionById($id);
        $permission->delete();

        return true;
    }
}
