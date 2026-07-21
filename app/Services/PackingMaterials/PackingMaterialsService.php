<?php

namespace App\Services\PackingMaterials;

use App\Errors\NotFoundError;
use App\Interfaces\PackingMaterials\PackingMaterialsServiceInterface;
use App\Models\PackingMaterial;
use Override;

class PackingMaterialsService implements PackingMaterialsServiceInterface
{
    #[Override]
    public function createPackingMaterial(array $data)
    {
        $newPackingMaterial = PackingMaterial::create($data);

        return $newPackingMaterial;
    }

    #[Override]
    public function getPackingMaterials(?string $limit)
    {
        $query = PackingMaterial::query();

        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->get();
    }

    #[Override]
    public function getPackingMaterialById(string $id)
    {
        $packingMaterial = PackingMaterial::find($id, ['*']);
        if (! $packingMaterial) {
            throw new NotFoundError('El material de empaque no existe');
        }

        return $packingMaterial;
    }

    #[Override]
    public function getPackingMaterialByCode(string $code)
    {
        $packingMaterial = PackingMaterial::where('code', '=', $code)->first();
        if (! $packingMaterial) {
            throw new NotFoundError('El material de empaque no existe');
        }

        return $packingMaterial;
    }

    #[Override]
    public function updatePackingMaterialById(string $id, array $data)
    {
        $packingMaterial = $this->getPackingMaterialByCode($id);
        $packingMaterial->update($data);

        return true;
    }

    #[Override]
    public function deletePackingMaterialById(string $id)
    {
        $packingMaterial = $this->getPackingMaterialByCode($id);
        $packingMaterial->delete();

        return true;
    }
}
