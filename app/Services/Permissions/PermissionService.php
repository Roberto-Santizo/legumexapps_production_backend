<?php

namespace App\Services\Permissions;

use App\Errors\NotFoundError;
use App\Interfaces\Permissions\PermissionServiceInterface;
use App\Models\Permission;
use Override;

class PermissionService implements PermissionServiceInterface
{
    public function createPermission(array $data)
    {
        $permission = Permission::create($data);

        return $permission;
    }

    public function getPermissions(?string $limit)
    {
        $query = Permission::query();

        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->get();
    }

    #[Override]
    public function getPermissionById(string $id)
    {
        $permission = Permission::find($id);
        if (! $permission) {
            throw new NotFoundError('El permiso no existe');
        }

        return $permission;

    }

    #[Override]
    public function updatePermissionById(array $data, string $id)
    {
        $permission = $this->getPermissionById($id);
        $permission->update($data);

        return true;
    }
}
