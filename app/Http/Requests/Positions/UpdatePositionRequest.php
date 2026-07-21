<?php

namespace App\Http\Requests\Positions;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePositionRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:255', Rule::unique('positions', 'code')->ignore($this->route('position'), 'id')],
            'activity' => ['required', 'string', 'max:255'],
            'line_id' => ['required', 'integer', 'exists:lines,id'],
            'status' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El código es requerido.',
            'code.string' => 'El código debe ser una cadena de texto.',
            'code.max' => 'El código no puede tener más de 255 caracteres.',
            'code.unique' => 'El código ya existe.',
            'activity.required' => 'La actividad es requerida.',
            'activity.string' => 'La actividad debe ser una cadena de texto.',
            'activity.max' => 'La actividad no puede tener más de 255 caracteres.',
            'line_id.required' => 'La línea es requerida.',
            'line_id.integer' => 'La línea debe ser un número entero.',
            'line_id.exists' => 'La línea no existe.',
            'status.boolean' => 'El estado debe ser verdadero o falso.',
        ];
    }
}
