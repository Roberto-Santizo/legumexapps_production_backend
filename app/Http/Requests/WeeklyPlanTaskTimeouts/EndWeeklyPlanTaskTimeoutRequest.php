<?php

namespace App\Http\Requests\WeeklyPlanTaskTimeouts;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EndWeeklyPlanTaskTimeoutRequest extends FormRequest
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
            'observation' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'observation.string' => 'La observación debe ser un texto.',
            'observation.max' => 'La observación no puede superar los 500 caracteres.',
        ];
    }
}
