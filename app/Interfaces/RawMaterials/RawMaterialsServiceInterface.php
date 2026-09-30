<?php

namespace App\Interfaces\RawMaterials;

use Illuminate\Http\Request;

interface RawMaterialsServiceInterface
{
    public function createRawMaterial(array $data);

    public function getRawMaterials(?string $limit, Request $request);

    public function getRawMaterialById(string $id);

    public function getRawMaterialByCode(string $code);

    public function updateRawMaterialById(string $id, array $data);

    public function deleteRawMaterialById(string $id);

    public function uploadFile(mixed $file);
}
