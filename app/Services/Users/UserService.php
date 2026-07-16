<?php

namespace App\Services\Users;

use App\Errors\NotFoundError;
use App\Interfaces\Users\UserServiceInterface;
use App\Models\User;
use Illuminate\Http\Request;
use Override;

class UserService implements UserServiceInterface
{
    public function createUser(array $data)
    {
        $data['password'] = bcrypt($data['password']);
        $user = User::create($data);

        return $user;
    }

    #[Override]
    public function getUsers(?string $limit, Request $request)
    {
        $query = User::query();

        if ($request->query('username')) {
            $query->where('username', 'LIKE', '%'.$request->query('username').'%');
        }

        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->get();
    }

    #[Override]
    public function getUserById(string $id)
    {
        throw new \Exception('Not implemented');
    }

    #[Override]
    public function getUserByUsername(string $username)
    {
        $user = User::where('username', $username)->first();
        if (! $user) {
            throw new NotFoundError('El usuario no existe');
        }

        return $user;

    }

    #[Override]
    public function updateUserById(array $data, string $id)
    {
        $user = $this->getUserByUsername($id);
        $data['password'] = bcrypt($data['password']);
        $user->update($data);

        return true;
    }

    #[Override]
    public function getPermissionsByUser(string $username)
    {
        $user = $this->getUserByUsername($username);

        return $user->permissions;
    }
}
