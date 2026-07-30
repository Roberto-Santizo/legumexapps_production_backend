<?php

namespace App\Http\Requests\WeeklyPlanTasks;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AssignOperationDateRequest extends FormRequest
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
            'tasksIds' => ['required', 'array', 'min:1'],
            'tasksIds.*' => ['integer', 'exists:weekly_plan_tasks,id'],
            'operation_date' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'tasksIds.required' => 'Las tareas son obligatorias.',
            'tasksIds.array' => 'Las tareas deben ser un arreglo.',
            'tasksIds.min' => 'Debe seleccionar al menos una tarea.',
            'tasksIds.*.integer' => 'El identificador de la tarea debe ser un número entero.',
            'tasksIds.*.exists' => 'Una o más tareas no existen.',

            'operation_date.required' => 'La fecha de operación es requerida.',
            'operation_date.date' => 'La fecha de operación debe ser una fecha válida.',
        ];
    }
}
