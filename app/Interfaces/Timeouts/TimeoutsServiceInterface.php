<?php

namespace App\Interfaces\Timeouts;

interface TimeoutsServiceInterface
{
    public function createTimeout(array $data);

    public function getTimeouts(?string $limit);

    public function getTimeoutById(string $id);

    public function updateTimeoutById(string $id, array $data);

    public function deleteTimeoutById(string $id);
}
