<?php

namespace App\Services\Skus;

use App\Errors\NotFoundError;
use App\Interfaces\Skus\SkusServiceInterface;
use App\Models\Sku;
use Override;

class SkusService implements SkusServiceInterface
{
    #[Override]
    public function createSku(array $data)
    {
        $newSku = Sku::create($data);
        return $newSku;
    }

    #[Override]
    public function getSkus(?string $limit)
    {
        $query = Sku::query();

        if($limit) return $query->paginate($limit);

        return $query->get();
    }

    #[Override]
    public function getSkuById(string $id)
    {
        $sku = Sku::find($id, ['*']);
        if(!$sku) throw new NotFoundError("El SKU no existe");
        return $sku;
        
    }

    #[Override]
    public function getSkuByCode(string $code)
    {
        $sku = Sku::where('code', '=', $code)->first();
        if(!$sku) throw new NotFoundError("El SKU no existe");
        return $sku;
        
    }

    #[Override]
    public function updateSkuById(string $id, array $data)
    {
        $sku = $this->getSkuByCode($id);
        $sku->update($data);
        return true;
    }

    #[Override]
    public function deleteSkuById(string $id)
    {
        $sku = $this->getSkuByCode($id);
        $sku->delete();
        return true;
    }
}
