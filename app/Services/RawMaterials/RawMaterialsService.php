<?php

namespace App\Services\RawMaterials;

use App\Errors\NotFoundError;
use App\Interfaces\RawMaterials\RawMaterialsServiceInterface;
use App\Models\RawMaterial;
use Override;

class RawMaterialsService implements RawMaterialsServiceInterface
{
    #[Override]
    public function createRawMaterial(array $data)
    {
        $newRawMaterial = RawMaterial::create($data);

        return $newRawMaterial;
    }

    #[Override]
    public function getRawMaterials(?string $limit)
    {
        $query = RawMaterial::query();

        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->get();
    }

    #[Override]
    public function getRawMaterialById(string $id)
    {
        $rawMaterial = RawMaterial::find($id, ['*']);
        if (! $rawMaterial) {
            throw new NotFoundError('La materia prima no existe');
        }

        return $rawMaterial;
    }

    #[Override]
    public function getRawMaterialByCode(string $code)
    {
        $rawMaterial = RawMaterial::where('code', '=', $code)->first();
        if (! $rawMaterial) {
            throw new NotFoundError('La materia prima no existe');
        }

        return $rawMaterial;
    }

    #[Override]
    public function updateRawMaterialById(string $id, array $data)
    {
        $rawMaterial = $this->getRawMaterialByCode($id);
        $rawMaterial->update($data);

        return true;
    }

    #[Override]
    public function deleteRawMaterialById(string $id)
    {
        $rawMaterial = $this->getRawMaterialByCode($id);
        $rawMaterial->delete();

        return true;
    }
}
