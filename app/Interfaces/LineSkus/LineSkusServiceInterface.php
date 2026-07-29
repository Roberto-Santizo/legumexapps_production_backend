<?php

namespace App\Interfaces\LineSkus;

use Illuminate\Http\Request;

interface LineSkusServiceInterface
{
    public function createLineSku(array $data);

    public function getLineSkus(?string $limit, Request $request);

    public function getLineSkuById(string $id);

    public function updateLineSkuById(string $id, array $data);

    public function deleteLineSkuById(string $id);
}
