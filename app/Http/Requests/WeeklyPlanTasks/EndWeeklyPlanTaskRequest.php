<?php

namespace App\Http\Requests\WeeklyPlanTasks;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EndWeeklyPlanTaskRequest extends FormRequest
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
            'produced_boxes' => ['required', 'integer', 'min:0'],
            'weighed_pounds' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'produced_boxes.required' => 'Las cajas producidas son obligatorias.',
            'produced_boxes.integer' => 'Las cajas producidas deben ser un número entero.',
            'produced_boxes.min' => 'Las cajas producidas no pueden ser negativas.',

            'weighed_pounds.required' => 'Las libras pesadas son obligatorias.',
            'weighed_pounds.numeric' => 'Las libras pesadas deben ser un valor numérico.',
            'weighed_pounds.min' => 'Las libras pesadas no pueden ser negativas.',
        ];
    }
}
