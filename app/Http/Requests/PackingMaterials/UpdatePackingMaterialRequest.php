<?php

namespace App\Http\Requests\PackingMaterials;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePackingMaterialRequest extends FormRequest
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
            'name' => ['required', 'string'],
            'description' => ['required', 'string'],
            'code' => ['required', 'string', Rule::unique('packing_materials', 'code')->ignore($this->route('packing_material'), 'code')],
            'blocked' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.string' => 'El nombre debe ser una cadena de texto.',

            'description.required' => 'La descripción es obligatoria.',
            'description.string' => 'La descripción debe ser una cadena de texto.',

            'code.required' => 'El código es obligatorio.',
            'code.unique' => 'El código ya existe.',
            'code.string' => 'El código debe ser una cadena de texto.',

            'blocked.boolean' => 'El campo bloqueado debe ser verdadero o falso.',
        ];
    }
}
