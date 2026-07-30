<?php

namespace App\Services\PackingMaterialTransactionItems;

use App\Errors\NotFoundError;
use App\Interfaces\PackingMaterialTransactionItems\PackingMaterialTransactionItemsServiceInterface;
use App\Models\PackingMaterialTransactionItem;
use Override;

class PackingMaterialTransactionItemsService implements PackingMaterialTransactionItemsServiceInterface
{
    #[Override]
    public function createPackingMaterialTransactionItem(array $data)
    {
        return PackingMaterialTransactionItem::create($data);
    }

    #[Override]
    public function getPackingMaterialTransactionItems(?string $limit)
    {
        $query = PackingMaterialTransactionItem::query();

        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->get();
    }

    #[Override]
    public function getPackingMaterialTransactionItemById(string $id)
    {
        $packingMaterialTransactionItem = PackingMaterialTransactionItem::find($id);
        if (! $packingMaterialTransactionItem) {
            throw new NotFoundError('El detalle de la transacción de material de empaque no existe');
        }

        return $packingMaterialTransactionItem;
    }

    #[Override]
    public function updatePackingMaterialTransactionItemById(string $id, array $data)
    {
        $packingMaterialTransactionItem = $this->getPackingMaterialTransactionItemById($id);
        $packingMaterialTransactionItem->update($data);

        return true;
    }

    #[Override]
    public function deletePackingMaterialTransactionItemById(string $id)
    {
        $packingMaterialTransactionItem = $this->getPackingMaterialTransactionItemById($id);
        $packingMaterialTransactionItem->delete();

        return true;
    }
}
