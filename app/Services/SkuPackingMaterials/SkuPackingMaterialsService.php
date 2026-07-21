<?php

namespace App\Services\SkuPackingMaterials;

use App\Errors\NotFoundError;
use App\Interfaces\SkuPackingMaterials\SkuPackingMaterialsServiceInterface;
use App\Models\SkuPackingMaterial;
use Override;

class SkuPackingMaterialsService implements SkuPackingMaterialsServiceInterface
{
    #[Override]
    public function createSkuPackingMaterial(array $data)
    {
        $newSkuPackingMaterial = SkuPackingMaterial::create($data);

        return $newSkuPackingMaterial;
    }

    #[Override]
    public function getSkuPackingMaterials(string $skuId)
    {
        $items = SkuPackingMaterial::where('sku_id', '=', $skuId)->get();
        return $items;
    }

    #[Override]
    public function getSkuPackingMaterialById(string $id)
    {
        $skuPackingMaterial = SkuPackingMaterial::find($id);
        if (! $skuPackingMaterial) {
            throw new NotFoundError('El material de empaque del SKU no existe');
        }

        return $skuPackingMaterial;
    }

    #[Override]
    public function updateSkuPackingMaterialById(string $id, array $data)
    {
        $skuPackingMaterial = $this->getSkuPackingMaterialById($id);
        $skuPackingMaterial->update($data);

        return true;
    }

    #[Override]
    public function deleteSkuPackingMaterialById(string $id)
    {
        $skuPackingMaterial = $this->getSkuPackingMaterialById($id);
        $skuPackingMaterial->delete();

        return true;
    }
}
