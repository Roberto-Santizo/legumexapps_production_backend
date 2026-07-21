<?php

namespace App\Interfaces\Positions;

use Illuminate\Http\Request;

interface PositionsServiceInterface
{
    public function createPosition(array $data);

    public function getPositions(?string $limit, Request $request);

    public function getPositionById(string $id);

    public function updatePositionById(array $data, string $id);

    public function deletePositionById(string $id);
}
