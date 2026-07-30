<?php

namespace App\Http\Requests\PackingMaterialTransactions;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePackingMaterialTransactionRequest extends FormRequest
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
            'reference' => ['required', 'string'],
            'responsable' => ['required', 'string'],
            'observations' => ['nullable', 'string'],
            'responsable_signature' => ['required', 'string'],
            'user_signature' => ['required', 'string'],
            'type' => ['required', 'integer'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'weekly_plan_task_id' => ['nullable', 'integer', 'exists:weekly_plan_tasks,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'reference.required' => 'La referencia es obligatoria.',
            'reference.string' => 'La referencia debe ser una cadena de texto.',

            'responsable.required' => 'El responsable es obligatorio.',
            'responsable.string' => 'El responsable debe ser una cadena de texto.',

            'observations.string' => 'Las observaciones deben ser una cadena de texto.',

            'responsable_signature.required' => 'La firma del responsable es obligatoria.',
            'responsable_signature.string' => 'La firma del responsable debe ser una cadena de texto.',

            'user_signature.required' => 'La firma del usuario es obligatoria.',
            'user_signature.string' => 'La firma del usuario debe ser una cadena de texto.',

            'type.required' => 'El tipo es obligatorio.',
            'type.integer' => 'El tipo debe ser un número entero.',

            'user_id.required' => 'El usuario es obligatorio.',
            'user_id.integer' => 'El usuario debe ser un número entero.',
            'user_id.exists' => 'El usuario no existe.',

            'weekly_plan_task_id.integer' => 'La tarea del plan semanal debe ser un número entero.',
            'weekly_plan_task_id.exists' => 'La tarea del plan semanal no existe.',
        ];
    }
}
