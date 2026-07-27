<?php

namespace App\Interfaces\SkuRawMaterials;

interface SkuRawMaterialsServiceInterface
{
    public function createSkuRawMaterial(array $data);

    public function getSkuRawMaterials(string $skuId);

    public function getSkuRawMaterialById(string $id);

    public function updateSkuRawMaterialById(string $id, array $data);

    public function deleteSkuRawMaterialById(string $id);
}
