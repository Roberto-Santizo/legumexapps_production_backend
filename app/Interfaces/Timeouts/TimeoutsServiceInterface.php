<?php

namespace App\Interfaces\Timeouts;

use Illuminate\Http\Request;

interface TimeoutsServiceInterface
{
    public function createTimeout(array $data);

    public function getTimeouts(?string $limit, Request $request);

    public function getTimeoutById(string $id);

    public function updateTimeoutById(string $id, array $data);

    public function deleteTimeoutById(string $id);

    public function uploadFile(mixed $file);
}
