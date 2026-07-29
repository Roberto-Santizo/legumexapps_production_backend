<?php

namespace App\Http\Requests\SkuRawMaterials;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSkuRawMaterialRequest extends FormRequest
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
            'percentage' => ['required', 'numeric'],
            'stock_keeping_unit_code' => ['required', 'exists:stock_keeping_units,code'],
            'raw_material_id' => ['required', 'exists:raw_materials,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'percentage.required' => 'El porcentaje es obligatorio.',
            'percentage.numeric' => 'El porcentaje debe ser un valor numérico.',

            'stock_keeping_unit_code.required' => 'El SKU es obligatorio.',
            'stock_keeping_unit_code.exists' => 'El SKU no existe.',

            'raw_material_id.required' => 'El material crudo es obligatorio.',
            'raw_material_id.exists' => 'El material crudo no existe.',
        ];
    }
}
