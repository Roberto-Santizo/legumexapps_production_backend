<?php

namespace App\Http\Resources\CaptureFields;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaptureFieldResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => $this->label,
            'data_type' => $this->data_type,
            'capture_type' => $this->capture_type,
            'is_system' => $this->is_system,
            'is_calculated' => $this->is_calculated,
            'depends_on' => $this->depends_on ?? [],
            'options' => $this->options,
            'is_assigned' => (bool) $this->line_fields_exists,
        ];
    }
}
