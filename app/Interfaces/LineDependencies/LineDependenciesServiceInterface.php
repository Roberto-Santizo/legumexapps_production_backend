<?php

namespace App\Interfaces\LineDependencies;

interface LineDependenciesServiceInterface
{
    public function create(array $data);

    public function get(?string $lineId);

    public function delete(string $id);
}
