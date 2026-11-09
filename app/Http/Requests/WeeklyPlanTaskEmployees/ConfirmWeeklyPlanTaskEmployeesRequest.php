<?php

namespace App\Http\Requests\WeeklyPlanTaskEmployees;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmWeeklyPlanTaskEmployeesRequest extends FormRequest
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
            'employees' => ['required', 'array'],
            'employees.*.code' => ['required', 'distinct'],
            'employees.*.name' => ['required', 'distinct'],
            'employees.*.position' => ['required', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'employees.array' => 'Los empleados deben ser una lista.',
            'employees.*.code' => 'El código del empleado es requerido',
            'employees.*.name' => 'El nombre del empleado es requerido',
            'employees.*.position' => 'La posición es requerida',
        ];
    }
}
