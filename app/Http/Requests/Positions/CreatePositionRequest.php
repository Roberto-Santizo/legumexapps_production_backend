<?php

namespace App\Http\Requests\Positions;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePositionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $role = $this->user()->role;
        if ($role != 'admin') {
            return false;
        }

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
            'code' => ['required', 'string', 'max:255', Rule::unique('positions', 'code')->where('line_sku_id', $this->input('line_sku_id'))],
            'activity_id' => ['required', 'integer', 'exists:activities,id'],
            'line_sku_id' => ['required', 'integer', 'exists:line_stock_keeping_units,id'],
            'status' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El código es requerido.',
            'code.string' => 'El código debe ser una cadena de texto.',
            'code.max' => 'El código no puede tener más de 255 caracteres.',
            'code.unique' => 'El código ya existe en el SKU de línea.',
            'activity_id.required' => 'La actividad es requerida.',
            'activity_id.integer' => 'La actividad debe ser un número entero.',
            'activity_id.exists' => 'La actividad no existe.',
            'line_sku_id.required' => 'El SKU de línea es requerido.',
            'line_sku_id.integer' => 'El SKU de línea debe ser un número entero.',
            'line_sku_id.exists' => 'El SKU de línea no existe.',
            'status.boolean' => 'El estado debe ser verdadero o falso.',
        ];
    }
}
