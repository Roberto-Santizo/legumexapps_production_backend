<?php

namespace App\Http\Requests\LineDependencies;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class CreateLineDependencyRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'line_id' =>            ['required', 'numeric', 'exists:lines,id'],
            'line_dependent_id' =>   ['required', 'numeric', 'exists:lines,id']
        ];
    }

    #[Override]
    public function messages()
    {
        return [
            'line_id.required' => 'La línea base es requerida',
            'line_id.numeric' => 'La línea debe de ser un dato númerico',
            'line_id.exists' => 'La línea no existe',

            'line_dependent_id.required' => 'La línea dependiente es requerida',
            'line_dependent_id.numeric' => 'La línea dependiente debe de ser un dato númerico',
            'line_dependent_id.exists' => 'La línea dependiente no existe',
        ];
    }
}
