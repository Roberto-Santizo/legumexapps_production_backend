<?php

namespace App\Interfaces\Users;

use Illuminate\Http\Request;

interface UserServiceInterface
{
    public function createUser(array $data);

    public function getUsers(?string $limit, Request $request);

    public function getUserById(string $id);

    public function getUserByUsername(string $username);

    public function updateUserById(array $data, string $id);

    public function getPermissionsByUser(string $username);
}
