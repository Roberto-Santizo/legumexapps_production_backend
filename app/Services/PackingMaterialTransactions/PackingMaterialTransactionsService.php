<?php

namespace App\Services\PackingMaterialTransactions;

use App\Errors\NotFoundError;
use App\Interfaces\PackingMaterialTransactions\PackingMaterialTransactionsServiceInterface;
use App\Models\PackingMaterialTransaction;
use Override;

class PackingMaterialTransactionsService implements PackingMaterialTransactionsServiceInterface
{
    #[Override]
    public function createPackingMaterialTransaction(array $data)
    {
        return PackingMaterialTransaction::create($data);
    }

    #[Override]
    public function getPackingMaterialTransactions(?string $limit)
    {
        $query = PackingMaterialTransaction::query();

        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->get();
    }

    #[Override]
    public function getPackingMaterialTransactionById(string $id)
    {
        $packingMaterialTransaction = PackingMaterialTransaction::find($id);
        if (! $packingMaterialTransaction) {
            throw new NotFoundError('La transacción de material de empaque no existe');
        }

        return $packingMaterialTransaction;
    }

    #[Override]
    public function updatePackingMaterialTransactionById(array $data, string $id)
    {
        $packingMaterialTransaction = $this->getPackingMaterialTransactionById($id);
        $packingMaterialTransaction->update($data);

        return true;
    }

    #[Override]
    public function deletePackingMaterialTransactionById(string $id)
    {
        $packingMaterialTransaction = $this->getPackingMaterialTransactionById($id);
        $packingMaterialTransaction->delete();

        return true;
    }
}
