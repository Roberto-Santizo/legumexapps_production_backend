<?php

namespace App\Http\Requests\PackingMaterialTransactions;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreatePackingMaterialTransactionRequest extends FormRequest
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
            'weekly_plan_task_id' => ['nullable', 'integer', 'exists:weekly_plan_tasks,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity' => ['required', 'numeric'],
            'items.*.lote' => ['required', 'string'],
            'items.*.destination' => ['nullable', 'string'],
            'items.*.packing_material_id' => ['required', 'integer', 'exists:packing_materials,id'],
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

            'weekly_plan_task_id.integer' => 'La tarea del plan semanal debe ser un número entero.',
            'weekly_plan_task_id.exists' => 'La tarea del plan semanal no existe.',

            'items.required' => 'Los detalles de la transacción son obligatorios.',
            'items.array' => 'Los detalles de la transacción deben ser un arreglo.',
            'items.min' => 'La transacción debe tener al menos un detalle.',

            'items.*.quantity.required' => 'La cantidad de cada detalle es obligatoria.',
            'items.*.quantity.numeric' => 'La cantidad de cada detalle debe ser un número.',

            'items.*.lote.required' => 'El lote de cada detalle es obligatorio.',
            'items.*.lote.string' => 'El lote de cada detalle debe ser una cadena de texto.',

            'items.*.destination.string' => 'El destino de cada detalle debe ser una cadena de texto.',

            'items.*.packing_material_id.required' => 'El material de empaque de cada detalle es obligatorio.',
            'items.*.packing_material_id.integer' => 'El material de empaque de cada detalle debe ser un número entero.',
            'items.*.packing_material_id.exists' => 'El material de empaque no existe.',
        ];
    }
}
