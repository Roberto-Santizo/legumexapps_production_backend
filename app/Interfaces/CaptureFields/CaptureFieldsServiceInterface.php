<?php

namespace App\Interfaces\CaptureFields;

use Illuminate\Http\Request;

interface CaptureFieldsServiceInterface
{
    public function createCaptureField(array $data);

    public function getCaptureFields(?string $limit, Request $request);

    public function getCaptureFieldById(string $id);

    public function updateCaptureFieldById(string $id, array $data);

    public function deleteCaptureFieldById(string $id);
}
