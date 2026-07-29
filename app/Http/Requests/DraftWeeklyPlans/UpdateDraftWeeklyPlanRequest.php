<?php

namespace App\Http\Requests\DraftWeeklyPlans;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDraftWeeklyPlanRequest extends FormRequest
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
            'week' => ['required', 'integer'],
            'year' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'week.required' => 'La semana es obligatoria.',
            'week.integer' => 'La semana debe ser un número entero.',

            'year.required' => 'El año es obligatorio.',
            'year.integer' => 'El año debe ser un número entero.',
        ];
    }
}
