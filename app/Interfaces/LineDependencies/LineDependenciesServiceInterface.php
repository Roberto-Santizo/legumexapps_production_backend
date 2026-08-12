<?php

namespace App\Interfaces\LineDependencies;

use Illuminate\Http\Request;

interface LineDependenciesServiceInterface
{
    public function create(array $data);
    public function get(string $lineId);
    public function delete(string $id);
}
