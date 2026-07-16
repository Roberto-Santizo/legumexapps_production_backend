<?php

namespace App\Http\Requests\Lines;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLineRequest extends FormRequest
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
            'name' =>           ['required', 'string'],
            'code'=>            ['required', 'string', Rule::unique('lines', 'code')->ignore($this->route('line'), 'code')],
            'shift'=>           ['required', 'numeric']
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'   => 'El nombre es obligatorio.',
            'name.string'     => 'El nombre debe ser una cadena de texto.',

            'code.required'   => 'El código es obligatorio.',
            'code.unique'     => 'El código ya existe.',
            'code.string'     => 'El código debe ser una cadena de texto.',

            'shift.required'  => 'El turno es obligatorio.',
            'shift.numeric'   => 'El turno debe ser un valor numérico.',
        ];
    }
}
