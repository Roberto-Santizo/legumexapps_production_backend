<?php

namespace App\Http\Requests\WeeklyPlanTaskEmployees;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWeeklyPlanTaskEmployeeActivityRequest extends FormRequest
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
            'line_activity_id' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'line_activity_id.required' => 'La actividad es requerida',
            'line_activity_id.integer' => 'La actividad debe ser un número entero',
        ];
    }
}
