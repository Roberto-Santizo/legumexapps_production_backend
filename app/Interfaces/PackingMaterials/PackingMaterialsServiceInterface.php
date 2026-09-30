<?php

namespace App\Interfaces\PackingMaterials;

use Illuminate\Http\Request;

interface PackingMaterialsServiceInterface
{
    public function createPackingMaterial(array $data);

    public function getPackingMaterials(?string $limit, Request $request);

    public function getPackingMaterialById(string $id);

    public function getPackingMaterialByCode(string $code);

    public function updatePackingMaterialById(string $id, array $data);

    public function deletePackingMaterialById(string $id);

    public function uploadFile(mixed $file);
}
