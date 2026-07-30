<?php

namespace App\Interfaces\PackingMaterialTransactions;

interface PackingMaterialTransactionsServiceInterface
{
    public function createPackingMaterialTransaction(array $data);

    public function getPackingMaterialTransactions(?string $limit);

    public function getPackingMaterialTransactionById(string $id);

    public function updatePackingMaterialTransactionById(array $data, string $id);

    public function deletePackingMaterialTransactionById(string $id);
}
