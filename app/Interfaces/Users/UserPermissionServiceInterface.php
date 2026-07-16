<?php

namespace App\Interfaces\Users;

interface UserPermissionServiceInterface
{
    public function addPermissionToUser(array $data);

    public function getUserPermissions(?string $id);

    public function getUserPermissionById(string $id);

    public function updateUserPermissionById(array $data, string $id);

    public function deleteUserPermissionById(string $id);
}
