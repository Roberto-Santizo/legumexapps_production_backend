<?php

namespace App\Interfaces\LineSkuActivities;

interface LineSkuActivitiesServiceInterface
{
    public function getLineSkuActivities(string $lineSkuId);

    public function createLineSkuActivity(string $lineSkuId, array $data);

    public function getLineSkuActivityById(string $id);

    public function updateLineSkuActivityById(string $id, array $data);

    public function deleteLineSkuActivityById(string $id);
}
