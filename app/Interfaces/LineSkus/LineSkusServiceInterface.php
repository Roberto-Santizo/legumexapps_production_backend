<?php

namespace App\Interfaces\LineSkus;

interface LineSkusServiceInterface
{
    public function createLineSku(array $data);

    public function getLineSkus(?string $limit);

    public function getLineSkuById(string $id);

    public function updateLineSkuById(string $id, array $data);

    public function deleteLineSkuById(string $id);
}
