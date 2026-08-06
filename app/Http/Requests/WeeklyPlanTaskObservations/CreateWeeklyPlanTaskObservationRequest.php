<?php

namespace App\Http\Requests\WeeklyPlanTaskObservations;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateWeeklyPlanTaskObservationRequest extends FormRequest
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
            'observation' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'weekly_plan_task_id.required' => 'La tarea del plan semanal es obligatoria.',
            'weekly_plan_task_id.integer' => 'La tarea del plan semanal debe ser un número entero.',
            'weekly_plan_task_id.exists' => 'La tarea del plan semanal no existe.',

            'observation.required' => 'La observación es obligatoria.',
            'observation.string' => 'La observación debe ser una cadena de texto.',
            'observation.max' => 'La observación no puede superar los 1000 caracteres.',
        ];
    }
}
