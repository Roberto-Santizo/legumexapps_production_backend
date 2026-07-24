<?php

namespace App\Http\Requests\WeeklyPlanTasks;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateWeeklyPlanTaskRequest extends FormRequest
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
            'boxes' =>              ['required', 'integer'],
            'destination' =>        ['required', 'string'],
            'operation_date' =>     ['required', 'date'],
            'weekly_plan_id' =>     ['required', 'integer', 'exists:weekly_plans,id'],
            'line_sku_id' =>        ['required', 'integer', 'exists:line_stock_keeping_units,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'boxes.required' =>             'Las cajas son obligatorias.',
            'boxes.integer' =>              'Las cajas deben ser un número entero.',

            'destination.required' =>       'El destino es obligatorio.',
            'destination.string' =>         'El destino debe ser una cadena de texto.',

            'operation_date.required' =>    'La fecha de operación es requerida.',
            'operation_date.date' =>        'La fecha de operación debe ser una fecha válida.',
            'start_date.date' =>            'La fecha de inicio debe ser una fecha válida.',
            'end_date.date' =>              'La fecha de fin debe ser una fecha válida.',

            'weekly_plan_id.required' =>    'El plan semanal es obligatorio.',
            'weekly_plan_id.integer' =>     'El plan semanal debe ser un número entero.',
            'weekly_plan_id.exists' =>      'El plan semanal no existe.',

            'line_sku_id.required' =>       'La linea es obligatoria.',
            'line_sku_id.integer' =>        'La linea debe ser un número entero.',
            'line_sku_id.exists' =>         'La linea no existe.',
        ];
    }
}
