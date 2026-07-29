<?php

namespace App\Http\Requests\DraftWeeklyPlanTasks;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDraftWeeklyPlanTaskRequest extends FormRequest
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
            'boxes' => ['required', 'integer'],
            'destination' => ['required', 'string'],
            'operation_date' => ['nullable', 'date'],
            'draft_weekly_plan_id' => ['required', 'integer', 'exists:draft_weekly_plans,id'],
            'sku_id' => ['required', 'integer', 'exists:stock_keeping_units,id'],
            'line_id' => ['nullable', 'integer', 'exists:lines,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'boxes.required' => 'Las cajas son obligatorias.',
            'boxes.integer' => 'Las cajas deben ser un número entero.',

            'destination.required' => 'El destino es obligatorio.',
            'destination.string' => 'El destino debe ser una cadena de texto.',

            'operation_date.date' => 'La fecha de operación debe ser una fecha válida.',

            'draft_weekly_plan_id.required' => 'El plan borrador es obligatorio.',
            'draft_weekly_plan_id.integer' => 'El plan borrador debe ser un número entero.',
            'draft_weekly_plan_id.exists' => 'El plan borrador no existe.',

            'sku_id.required' => 'El sku es obligatorio.',
            'sku_id.integer' => 'El sku debe ser un número entero.',
            'sku_id.exists' => 'El sku no existe.',

            'line_id.integer' => 'La linea debe ser un número entero.',
            'line_id.exists' => 'La linea no existe.',
        ];
    }
}
