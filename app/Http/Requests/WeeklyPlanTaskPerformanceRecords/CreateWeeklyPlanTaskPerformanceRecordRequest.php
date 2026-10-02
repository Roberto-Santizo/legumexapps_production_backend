<?php

namespace App\Http\Requests\WeeklyPlanTaskPerformanceRecords;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateWeeklyPlanTaskPerformanceRecordRequest extends FormRequest
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
            'weekly_plan_task_id' => ['required', 'integer', 'exists:weekly_plan_tasks,id'],
            'pallet_number' => ['nullable', 'integer', 'min:1'],
            'boxes' => ['nullable', 'integer', 'min:0'],
            'weighed_pounds' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'weekly_plan_task_id.required' => 'La tarea del plan semanal es obligatoria.',
            'weekly_plan_task_id.integer' => 'La tarea del plan semanal debe ser un número entero.',
            'weekly_plan_task_id.exists' => 'La tarea del plan semanal no existe.',

            'pallet_number.integer' => 'El número de pallet debe ser un número entero.',
            'pallet_number.min' => 'El número de pallet debe ser mayor a 0.',

            'boxes.integer' => 'Las cajas deben ser un número entero.',
            'boxes.min' => 'Las cajas no pueden ser negativas.',

            'weighed_pounds.required' => 'Las libras pesadas son obligatorias.',
            'weighed_pounds.numeric' => 'Las libras pesadas deben ser un valor numérico.',
            'weighed_pounds.min' => 'Las libras pesadas no pueden ser negativas.',
        ];
    }
}
