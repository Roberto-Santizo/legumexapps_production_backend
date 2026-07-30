<?php

namespace App\Interfaces\PackingMaterialTransactionItems;

interface PackingMaterialTransactionItemsServiceInterface
{
    public function createPackingMaterialTransactionItem(array $data);

    public function getPackingMaterialTransactionItems(?string $limit);

    public function getPackingMaterialTransactionItemById(string $id);

    public function updatePackingMaterialTransactionItemById(string $id, array $data);

    public function deletePackingMaterialTransactionItemById(string $id);
}
