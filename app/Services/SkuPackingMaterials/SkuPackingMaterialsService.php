<?php

namespace App\Services\SkuPackingMaterials;

use App\Errors\NotFoundError;
use App\Interfaces\SkuPackingMaterials\SkuPackingMaterialsServiceInterface;
use App\Models\Sku;
use App\Models\SkuPackingMaterial;
use Override;

class SkuPackingMaterialsService implements SkuPackingMaterialsServiceInterface
{
    #[Override]
    public function createSkuPackingMaterial(array $data)
    {
        $sku = Sku::where('code', '=', $data['sku_code'])->first();

        $payload = [
            'lbs_per_item' => $data['lbs_per_item'],
            'sku_id' => $sku->id,
            'packing_material_id' => $data['packing_material_id'],
        ];

        $newSkuPackingMaterial = SkuPackingMaterial::create($payload);

        return $newSkuPackingMaterial;
    }

    #[Override]
    public function getSkuPackingMaterials(string $skuId)
    {
        $query = SkuPackingMaterial::query();
        $query->whereHas('sku', function ($p0) use ($skuId) {
            $p0->where('code', 'LIKE', '%'.$skuId.'%');
        });

        return $query->get();
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
        $sku = Sku::where('code', '=', $data['sku_code'])->first();

        $payload = [
            'lbs_per_item' => $data['lbs_per_item'],
            'sku_id' => $sku->id,
            'packing_material_id' => $data['packing_material_id'],
        ];
        
        $skuPackingMaterial->update($payload);

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
