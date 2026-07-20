<?php

namespace App\Services\LineSkus;

use App\Errors\NotFoundError;
use App\Interfaces\LineSkus\LineSkusServiceInterface;
use App\Models\LineSku;
use Override;

class LineSkusService implements LineSkusServiceInterface
{
    #[Override]
    public function createLineSku(array $data)
    {
        $newLineSku = LineSku::create($data);

        return $newLineSku;
    }

    #[Override]
    public function getLineSkus(?string $limit)
    {
        $query = LineSku::query();

        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->get();
    }

    #[Override]
    public function getLineSkuById(string $id)
    {
        $lineSku = LineSku::find($id, ['*']);
        if (! $lineSku) {
            throw new NotFoundError('El SKU de línea no existe');
        }

        return $lineSku;
    }

    #[Override]
    public function updateLineSkuById(string $id, array $data)
    {
        $lineSku = $this->getLineSkuById($id);
        $lineSku->update($data);

        return true;
    }

    #[Override]
    public function deleteLineSkuById(string $id)
    {
        $lineSku = $this->getLineSkuById($id);
        $lineSku->delete();

        return true;
    }
}
