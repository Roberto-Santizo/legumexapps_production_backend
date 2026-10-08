<?php

namespace App\Http\Resources\LineFields;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LineFieldResource extends JsonResource
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
            'line_id' => $this->line_id,
            'capture_field_id' => $this->capture_field_id,
            'key' => $this->captureField->key,
            'label' => $this->label ?? $this->captureField->label,
            'field_label' => $this->captureField->label,
            'custom_label' => $this->label,
            'data_type' => $this->captureField->data_type,
            'is_system' => $this->captureField->is_system,
            'is_calculated' => $this->captureField->is_calculated,
            'depends_on' => $this->captureField->depends_on ?? [],
            'options' => $this->captureField->options,
            'is_required' => $this->is_required,
            'order' => $this->order,
        ];
    }
}
