<?php

namespace App\Interfaces\LineFields;

interface LineFieldsServiceInterface
{
    public function getLineFields(string $lineCode);

    public function createLineField(string $lineCode, array $data);

    public function getLineFieldById(string $id);

    public function updateLineFieldById(string $id, array $data);

    public function deleteLineFieldById(string $id);
}
