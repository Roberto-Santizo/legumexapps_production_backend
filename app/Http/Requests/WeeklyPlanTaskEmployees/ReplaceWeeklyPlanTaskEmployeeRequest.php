<?php

namespace App\Http\Requests\WeeklyPlanTaskEmployees;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReplaceWeeklyPlanTaskEmployeeRequest extends FormRequest
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
            'weekly_plan_employee_id' => ['required', 'integer', 'exists:weekly_plan_employees,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'weekly_plan_employee_id.required' => 'El empleado de reemplazo es requerido.',
            'weekly_plan_employee_id.integer' => 'El empleado de reemplazo debe ser un número entero.',
            'weekly_plan_employee_id.exists' => 'El empleado de reemplazo no existe.',
        ];
    }
}
