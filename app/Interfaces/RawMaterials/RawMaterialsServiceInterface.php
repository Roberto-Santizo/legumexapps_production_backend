<?php

namespace App\Interfaces\RawMaterials;

interface RawMaterialsServiceInterface
{
    public function createRawMaterial(array $data);

    public function getRawMaterials(?string $limit);

    public function getRawMaterialById(string $id);

    public function getRawMaterialByCode(string $code);

    public function updateRawMaterialById(string $id, array $data);

    public function deleteRawMaterialById(string $id);
}
