<?php

namespace App\Interfaces\SkuPackingMaterials;

interface SkuPackingMaterialsServiceInterface
{
    public function createSkuPackingMaterial(array $data);

    public function getSkuPackingMaterials(string $skuId);

    public function getSkuPackingMaterialById(string $id);

    public function updateSkuPackingMaterialById(string $id, array $data);

    public function deleteSkuPackingMaterialById(string $id);
}
