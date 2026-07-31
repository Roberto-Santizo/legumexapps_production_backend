<?php

namespace App\Interfaces\PackingMaterialTransactionItems;

use Illuminate\Http\Request;

interface PackingMaterialTransactionItemsServiceInterface
{
    public function createPackingMaterialTransactionItem(array $data);

    public function getPackingMaterialTransactionItems(?string $limit, Request $request);

    public function getPackingMaterialTransactionItemById(string $id);

    public function updatePackingMaterialTransactionItemById(string $id, array $data);

    public function deletePackingMaterialTransactionItemById(string $id);
}
