<?php

namespace App\Http\Requests\Clients;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
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
            'name' =>                   ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' =>          'El nombre es obligatorio.',
            'name.string' =>            'El nombre debe ser una cadena de texto.',
        ];
    }
}
