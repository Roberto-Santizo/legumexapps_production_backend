<?php

namespace App\Http\Requests\WeeklyPlanTasks;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWeeklyPlanTaskRequest extends FormRequest
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
                'produced_boxes' =>     ['nullable', 'integer'],
                'pallets' =>            ['required', 'numeric'],
                'produced_pallets' =>   ['nullable', 'numeric'],
                'hours' =>              ['required', 'numeric'],
                'weighed_pounds' =>     ['nullable', 'numeric'],
                'destination' =>        ['required', 'string'],
                'operation_date' =>     ['nullable', 'date'],
                'start_date' =>         ['nullable', 'date'],
                'end_date' =>           ['nullable', 'date'],
                'weekly_plan_id' =>     ['required', 'integer', 'exists:weekly_plans,id'],
                'line_sku_id' =>        ['required', 'integer', 'exists:line_stock_keeping_units,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'boxes.required' => 'Las cajas son obligatorias.',
            'boxes.integer' => 'Las cajas deben ser un número entero.',

            'produced_boxes.integer' => 'Las cajas producidas deben ser un número entero.',

            'pallets.required' => 'Los tarimas son obligatorios.',
            'pallets.numeric' => 'Los tarimas deben ser un valor numérico.',

            'produced_pallets.numeric' => 'Las tarimas producidas deben ser un valor numérico.',

            'hours.required' => 'Las horas son obligatorias.',
            'hours.numeric' => 'Las horas deben ser un valor numérico.',

            'weighed_pounds.required' => 'Las libras pesadas son obligatorias.',
            'weighed_pounds.numeric' => 'Las libras pesadas deben ser un valor numérico.',

            'destination.required' => 'El destino es obligatorio.',
            'destination.string' => 'El destino debe ser una cadena de texto.',

            'operation_date.date' => 'La fecha de operación debe ser una fecha válida.',
            'start_date.date' => 'La fecha de inicio debe ser una fecha válida.',
            'end_date.date' => 'La fecha de fin debe ser una fecha válida.',

            'weekly_plan_id.required' => 'El plan semanal es obligatorio.',
            'weekly_plan_id.integer' => 'El plan semanal debe ser un número entero.',
            'weekly_plan_id.exists' => 'El plan semanal no existe.',

            'line_sku_id.required' => 'El desempeño de línea es obligatorio.',
            'line_sku_id.integer' => 'El desempeño de línea debe ser un número entero.',
            'line_sku_id.exists' => 'El desempeño de línea no existe.',
        ];
    }
}
