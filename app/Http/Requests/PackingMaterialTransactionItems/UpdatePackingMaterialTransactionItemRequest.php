<?php

namespace App\Http\Requests\PackingMaterialTransactionItems;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePackingMaterialTransactionItemRequest extends FormRequest
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
            'quantity' => ['required', 'numeric'],
            'lote' => ['required', 'string'],
            'destination' => ['nullable', 'string'],
            'packing_material_id' => ['required', 'integer', 'exists:packing_materials,id'],
            'pm_transaction_id' => ['required', 'integer', 'exists:packing_material_transactions,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.required' => 'La cantidad es obligatoria.',
            'quantity.numeric' => 'La cantidad debe ser un valor numérico.',

            'lote.required' => 'El lote es obligatorio.',
            'lote.string' => 'El lote debe ser una cadena de texto.',

            'destination.string' => 'El destino debe ser una cadena de texto.',

            'packing_material_id.required' => 'El material de empaque es obligatorio.',
            'packing_material_id.integer' => 'El material de empaque debe ser un número entero.',
            'packing_material_id.exists' => 'El material de empaque no existe.',

            'pm_transaction_id.required' => 'La transacción de material de empaque es obligatoria.',
            'pm_transaction_id.integer' => 'La transacción de material de empaque debe ser un número entero.',
            'pm_transaction_id.exists' => 'La transacción de material de empaque no existe.',
        ];
    }
}
