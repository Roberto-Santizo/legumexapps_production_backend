<?php

namespace App\Http\Requests\WeeklyPlanTaskPerformanceRecords;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWeeklyPlanTaskPerformanceRecordRequest extends FormRequest
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
            'pallet_number' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'boxes' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'weighed_pounds' => ['sometimes', 'required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
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
