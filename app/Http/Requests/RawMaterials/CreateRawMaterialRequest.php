<?php

namespace App\Http\Requests\RawMaterials;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateRawMaterialRequest extends FormRequest
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
            'code' => ['required', 'string', 'unique:raw_materials,code'],
            'product_name' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El código es obligatorio.',
            'code.unique' => 'El código ya existe.',
            'code.string' => 'El código debe ser una cadena de texto.',

            'product_name.required' => 'El nombre del producto es obligatorio.',
            'product_name.string' => 'El nombre del producto debe ser una cadena de texto.',
        ];
    }
}
