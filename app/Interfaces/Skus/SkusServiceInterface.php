<?php

namespace App\Interfaces\Skus;

use Illuminate\Http\Request;

interface SkusServiceInterface
{
    public function createSku(array $data);

    public function getSkus(?string $limit, Request $request);

    public function getSkuById(string $id);

    public function getSkuByCode(string $code);

    public function updateSkuById(string $id, array $data);

    public function deleteSkuById(string $id);

    public function uploadFile(mixed $file);
}
