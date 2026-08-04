<?php

namespace App\Http\Requests\WeeklyPlanTaskObservations;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWeeklyPlanTaskObservationRequest extends FormRequest
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
            'observation' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'observation.required' => 'La observación es obligatoria.',
            'observation.string' =>   'La observación debe ser una cadena de texto.',
            'observation.max' =>      'La observación no puede superar los 1000 caracteres.',
        ];
    }
}
