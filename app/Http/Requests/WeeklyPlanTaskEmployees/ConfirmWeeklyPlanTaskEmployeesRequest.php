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
            'replacements' => ['nullable', 'array'],
            'replacements.*.weekly_plan_employee_id' => ['required', 'integer', 'distinct', 'exists:weekly_plan_employees,id'],
            'replacements.*.replacement_weekly_plan_employee_id' => ['required', 'integer', 'distinct', 'exists:weekly_plan_employees,id'],
            'additions' => ['nullable', 'array'],
            'additions.*' => ['integer', 'distinct', 'exists:weekly_plan_employees,id'],
            'removals' => ['nullable', 'array'],
            'removals.*' => ['integer', 'distinct', 'exists:weekly_plan_employees,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'replacements.array' => 'Los reemplazos deben ser una lista.',
            'replacements.*.weekly_plan_employee_id.required' => 'El empleado a reemplazar es requerido.',
            'replacements.*.weekly_plan_employee_id.integer' => 'El empleado a reemplazar debe ser un número entero.',
            'replacements.*.weekly_plan_employee_id.distinct' => 'El empleado a reemplazar está repetido.',
            'replacements.*.weekly_plan_employee_id.exists' => 'El empleado a reemplazar no existe.',
            'replacements.*.replacement_weekly_plan_employee_id.required' => 'El empleado de reemplazo es requerido.',
            'replacements.*.replacement_weekly_plan_employee_id.integer' => 'El empleado de reemplazo debe ser un número entero.',
            'replacements.*.replacement_weekly_plan_employee_id.distinct' => 'El empleado de reemplazo está repetido.',
            'replacements.*.replacement_weekly_plan_employee_id.exists' => 'El empleado de reemplazo no existe.',
            'additions.array' => 'Las altas deben ser una lista.',
            'additions.*.integer' => 'El empleado agregado debe ser un número entero.',
            'additions.*.distinct' => 'El empleado agregado está repetido.',
            'additions.*.exists' => 'El empleado agregado no existe.',
            'removals.array' => 'Las bajas deben ser una lista.',
            'removals.*.integer' => 'El empleado retirado debe ser un número entero.',
            'removals.*.distinct' => 'El empleado retirado está repetido.',
            'removals.*.exists' => 'El empleado retirado no existe.',
        ];
    }
}
