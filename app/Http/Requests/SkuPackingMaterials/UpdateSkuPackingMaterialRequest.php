<?php

namespace App\Http\Requests\SkuPackingMaterials;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSkuPackingMaterialRequest extends FormRequest
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
            'lbs_per_item' => ['required', 'numeric'],
            'sku_id' => ['required', 'exists:stock_keeping_units,id'],
            'packing_material_id' => ['required', 'exists:packing_materials,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'lbs_per_item.required' => 'Las libras por artículo son obligatorias.',
            'lbs_per_item.numeric' => 'Las libras por artículo deben ser un valor numérico.',

            'sku_id.required' => 'El SKU es obligatorio.',
            'sku_id.exists' => 'El SKU no existe.',

            'packing_material_id.required' => 'El material de empaque es obligatorio.',
            'packing_material_id.exists' => 'El material de empaque no existe.',
        ];
    }
}
