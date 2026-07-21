<?php

namespace App\Interfaces\PackingMaterials;

interface PackingMaterialsServiceInterface
{
    public function createPackingMaterial(array $data);

    public function getPackingMaterials(?string $limit);

    public function getPackingMaterialById(string $id);

    public function getPackingMaterialByCode(string $code);

    public function updatePackingMaterialById(string $id, array $data);

    public function deletePackingMaterialById(string $id);
}
