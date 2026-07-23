<?php

namespace App\Http\Requests\WeeklyPlanEmployees;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateWeeklyPlanEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $role = $this->user()->role;
        if ($role != 'admin') {
            return false;
        }

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
            'position_id' => ['required', 'integer', 'exists:positions,id'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'weekly_plan_id' => ['required', 'integer', 'exists:weekly_plans,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'position_id.required' => 'El puesto es requerido.',
            'position_id.integer' => 'El puesto debe ser un número entero.',
            'position_id.exists' => 'El puesto no existe.',
            'employee_id.required' => 'El empleado es requerido.',
            'employee_id.integer' => 'El empleado debe ser un número entero.',
            'employee_id.exists' => 'El empleado no existe.',
            'weekly_plan_id.required' => 'El plan semanal es requerido.',
            'weekly_plan_id.integer' => 'El plan semanal debe ser un número entero.',
            'weekly_plan_id.exists' => 'El plan semanal no existe.',
        ];
    }
}
