<?php

namespace App\Http\Requests\WeeklyPlanTaskEmployees;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddWeeklyPlanTaskEmployeeRequest extends FormRequest
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
            'weekly_plan_employee_id.required' => 'El empleado es requerido.',
            'weekly_plan_employee_id.integer' => 'El empleado debe ser un número entero.',
            'weekly_plan_employee_id.exists' => 'El empleado no existe.',
        ];
    }
}
