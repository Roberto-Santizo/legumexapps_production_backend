<?php

namespace App\Http\Requests\Skus;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateSkuRequest extends FormRequest
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
            'code' =>                   ['required', 'string', 'unique:stock_keeping_units,code'],
            'product_name' =>           ['required', 'string'],
            'presentation' =>           ['nullable', 'numeric'],
            'boxes_per_pallet' =>       ['nullable', 'integer'],
            'client_id' =>              ['required', 'exists:clients,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' =>                  'El código es obligatorio.',
            'code.unique' =>                    'El código ya existe.',
            'code.string' =>                    'El código debe ser una cadena de texto.',

            'product_name.required' =>          'El nombre del producto es obligatorio.',
            'product_name.string' =>            'El nombre del producto debe ser una cadena de texto.',

            'presentation.numeric' =>           'La presentación debe ser un valor numérico.',

            'boxes_per_pallet.integer' =>       'Las cajas por tarima deben ser un valor numérico.',

            'client_id.required' =>             'El cliente es obligatorio.',
            'client_id.exists' =>               'El cliente no existe.',
        ];
    }
}
