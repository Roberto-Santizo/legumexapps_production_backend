<?php

namespace App\Services\SkuRawMaterials;

use App\Errors\NotFoundError;
use App\Interfaces\SkuRawMaterials\SkuRawMaterialsServiceInterface;
use App\Models\Sku;
use App\Models\SkuRawMaterial;
use Override;

class SkuRawMaterialsService implements SkuRawMaterialsServiceInterface
{
    #[Override]
    public function createSkuRawMaterial(array $data)
    {
        $sku = Sku::where('code', '=', $data['stock_keeping_unit_code'])->first();

        $payload = [
            'percentage'=> $data['percentage'],
            'stock_keeping_unit_id' => $sku->id, 
            'raw_material_id'=> $data['raw_material_id']
        ];

        $newSkuRawMaterial = SkuRawMaterial::create($payload);

        return $newSkuRawMaterial;
    }

    #[Override]
    public function getSkuRawMaterials(string $skuId)
    {
        $query = SkuRawMaterial::query();
        $query->whereHas('sku', function ($p0) use ($skuId) {
            $p0->where('code', 'LIKE', '%'.$skuId.'%');
        });

        return $query->get();
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
        $sku = Sku::where('code', '=', $data['stock_keeping_unit_code'])->first();
        $skuRawMaterial = $this->getSkuRawMaterialById($id);

        $payload = [
            'percentage'=> $data['percentage'],
            'stock_keeping_unit_id' => $sku->id, 
            'raw_material_id'=> $data['raw_material_id']
        ];
        $skuRawMaterial->update($payload);

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
