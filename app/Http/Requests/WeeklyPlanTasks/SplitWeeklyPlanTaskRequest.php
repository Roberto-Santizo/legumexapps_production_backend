<?php

namespace App\Http\Requests\WeeklyPlanTasks;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SplitWeeklyPlanTaskRequest extends FormRequest
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
            'task_id' => ['required', 'integer', 'exists:weekly_plan_tasks,id'],
            'portions' => ['required', 'array', 'min:2'],
            'portions.*.boxes' => ['required', 'integer', 'min:1'],
            'portions.*.operation_date' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'task_id.required' => 'La tarea a dividir es obligatoria.',
            'task_id.integer' => 'El identificador de la tarea debe ser un número entero.',
            'task_id.exists' => 'La tarea no existe.',

            'portions.required' => 'Las porciones son obligatorias.',
            'portions.array' => 'Las porciones deben ser un arreglo.',
            'portions.min' => 'Debe dividir la tarea en al menos dos porciones.',

            'portions.*.boxes.required' => 'Las cajas de cada porción son obligatorias.',
            'portions.*.boxes.integer' => 'Las cajas de cada porción deben ser un número entero.',
            'portions.*.boxes.min' => 'Las cajas de cada porción deben ser al menos 1.',

            'portions.*.operation_date.required' => 'La fecha de operación de cada porción es requerida.',
            'portions.*.operation_date.date' => 'La fecha de operación de cada porción debe ser una fecha válida.',
        ];
    }
}
