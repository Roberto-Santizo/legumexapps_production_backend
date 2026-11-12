<?php

namespace App\Interfaces\LineActivities;

interface LineActivitiesServiceInterface
{
    public function getLineActivities(string $lineCode);

    public function createLineActivity(string $lineCode, array $data);

    public function getLineActivityById(string $id);

    public function updateLineActivityById(string $id, array $data);

    public function deleteLineActivityById(string $id);
}
