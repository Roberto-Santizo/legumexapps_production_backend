<?php

namespace App\Interfaces\Permissions;

interface PermissionServiceInterface
{
    public function createPermission(array $data);

    public function getPermissions(?string $limit);

    public function getPermissionById(string $id);

    public function updatePermissionById(array $data, string $id);
}
