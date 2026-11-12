<?php

namespace App\Http\Requests\LineActivities;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateLineActivityRequest extends FormRequest
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
            'activity_id' => ['required', 'integer', 'exists:activities,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'activity_id.required' => 'La actividad es obligatoria.',
            'activity_id.integer' => 'La actividad debe ser un número entero.',
            'activity_id.exists' => 'La actividad no existe.',

            'quantity.required' => 'La cantidad es obligatoria.',
            'quantity.integer' => 'La cantidad debe ser un número entero.',
            'quantity.min' => 'La cantidad debe ser al menos 1.',
        ];
    }
}
