<?php

namespace App\Http\Resources\CaptureFields;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaginatedCaptureFieldsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $captureFields = CaptureFieldResource::collection($this->items());

        return [
            'data' => $captureFields,
            'total' => $this->total(),
            'currentPage' => $this->currentPage(),
            'lastPage' => $this->lastPage(),
        ];
    }
}
