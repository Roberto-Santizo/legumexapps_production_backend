<?php

namespace App\Interfaces\Skus;

interface SkusServiceInterface
{
    public function createSku(array $data);

    public function getSkus(?string $limit);

    public function getSkuById(string $id);

    public function getSkuByCode(string $code);

    public function updateSkuById(string $id, array $data);

    public function deleteSkuById(string $id);
}
