<?php

namespace App\Interfaces\PackingMaterialTransactions;

use Illuminate\Http\Request;

interface PackingMaterialTransactionsServiceInterface
{
    public function createPackingMaterialTransaction(array $data);

    public function getPackingMaterialTransactions(?string $limit, Request $request);

    public function getPackingMaterialTransactionById(string $id);

    public function updatePackingMaterialTransactionById(array $data, string $id);

    public function deletePackingMaterialTransactionById(string $id);
}
