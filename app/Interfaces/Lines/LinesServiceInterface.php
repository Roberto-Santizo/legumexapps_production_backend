<?php

namespace App\Interfaces\Lines;

use Illuminate\Http\Request;

interface LinesServiceInterface
{
    public function createLine(array $data);
    public function getLines(Request $request, ?string $limit);
    public function getLineById(string $id);
    public function getLineByCode(string $code);
    public function updateLineById(string $id, array $data);
    public function deleteLineById(string $id);
}
