<?php

namespace App\Http\Requests\LineFields;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateLineFieldRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'capture_field_id' => ['required', 'integer', 'exists:capture_fields,id'],
            'is_required' => ['sometimes', 'boolean'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'label' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'capture_field_id.required' => 'El campo es obligatorio.',
            'capture_field_id.integer' => 'El campo debe ser un número entero.',
            'capture_field_id.exists' => 'El campo no existe.',

            'is_required.boolean' => 'El indicador de obligatorio debe ser verdadero o falso.',

            'order.integer' => 'El orden debe ser un número entero.',
            'order.min' => 'El orden no puede ser negativo.',

            'label.string' => 'La etiqueta debe ser una cadena de texto.',
            'label.max' => 'La etiqueta no debe exceder 100 caracteres.',
        ];
    }
}
