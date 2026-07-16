<?php

namespace App\Interfaces\Lines;

interface LinesServiceInterface
{
    public function createLine(array $data);
    public function getLines(?string $limit);
    public function getLineById(string $id);
    public function getLineByCode(string $code);
    public function updateLineById(string $id, array $data);
    public function deleteLineById(string $id);
}
