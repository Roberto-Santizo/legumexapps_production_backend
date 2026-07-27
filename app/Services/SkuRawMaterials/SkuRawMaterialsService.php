<?php

namespace App\Services\SkuRawMaterials;

use App\Errors\NotFoundError;
use App\Interfaces\SkuRawMaterials\SkuRawMaterialsServiceInterface;
use App\Models\SkuRawMaterial;
use Override;

class SkuRawMaterialsService implements SkuRawMaterialsServiceInterface
{
    #[Override]
    public function createSkuRawMaterial(array $data)
    {
        $newSkuRawMaterial = SkuRawMaterial::create($data);

        return $newSkuRawMaterial;
    }

    #[Override]
    public function getSkuRawMaterials(string $skuId)
    {
        $items = SkuRawMaterial::where('stock_keeping_unit_id', '=', $skuId)->get();

        return $items;
    }

    #[Override]
    public function getSkuRawMaterialById(string $id)
    {
        $skuRawMaterial = SkuRawMaterial::find($id);
        if (! $skuRawMaterial) {
            throw new NotFoundError('El material crudo del SKU no existe');
        }

        return $skuRawMaterial;
    }

    #[Override]
    public function updateSkuRawMaterialById(string $id, array $data)
    {
        $skuRawMaterial = $this->getSkuRawMaterialById($id);
        $skuRawMaterial->update($data);

        return true;
    }

    #[Override]
    public function deleteSkuRawMaterialById(string $id)
    {
        $skuRawMaterial = $this->getSkuRawMaterialById($id);
        $skuRawMaterial->delete();

        return true;
    }
}
