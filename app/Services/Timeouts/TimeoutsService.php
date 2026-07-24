<?php

namespace App\Services\Timeouts;

use App\Errors\NotFoundError;
use App\Interfaces\Timeouts\TimeoutsServiceInterface;
use App\Models\Timeout;
use Override;

class TimeoutsService implements TimeoutsServiceInterface
{
    #[Override]
    public function createTimeout(array $data)
    {
        $newTimeout = Timeout::create($data);

        return $newTimeout;
    }

    #[Override]
    public function getTimeouts(?string $limit)
    {
        $query = Timeout::query();

        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->get();
    }

    #[Override]
    public function getTimeoutById(string $id)
    {
        $timeout = Timeout::find($id, ['*']);
        if (! $timeout) {
            throw new NotFoundError('El tiempo muerto no existe');
        }

        return $timeout;
    }

    #[Override]
    public function updateTimeoutById(string $id, array $data)
    {
        $timeout = $this->getTimeoutById($id);
        $timeout->update($data);

        return true;
    }

    #[Override]
    public function deleteTimeoutById(string $id)
    {
        $timeout = $this->getTimeoutById($id);
        $timeout->delete();

        return true;
    }
}
