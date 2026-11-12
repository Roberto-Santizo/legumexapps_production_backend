<?php

namespace App\Http\Requests\Activities;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateActivityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize name (trim + uppercase) and unit (trim) before validating, so unique compares the final value.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => mb_strtoupper(trim($this->input('name')))]);
        }

        if (is_string($this->input('unit'))) {
            $this->merge(['unit' => trim($this->input('unit'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('activities', 'name')->ignore($this->route('id'))],
            'unit' => ['sometimes', 'required', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.string' => 'El nombre debe ser una cadena de texto.',
            'name.max' => 'El nombre no debe exceder 100 caracteres.',
            'name.unique' => 'La actividad ya existe.',

            'unit.required' => 'La unidad de medida es obligatoria.',
            'unit.string' => 'La unidad de medida debe ser una cadena de texto.',
            'unit.max' => 'La unidad de medida no debe exceder 50 caracteres.',
        ];
    }
}
