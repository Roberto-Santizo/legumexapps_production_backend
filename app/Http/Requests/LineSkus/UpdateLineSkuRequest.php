<?php

namespace App\Http\Requests\LineSkus;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLineSkuRequest extends FormRequest
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
            'sku_id' =>                     ['required', 'exists:stock_keeping_units,id'],
            'line_id' =>                    ['required', 'exists:lines,id'],
            'lbs_performance' =>            ['required', 'numeric'],
            'accepted_percentage' =>        ['required', 'numeric'],
            'payment_method' =>             ['required', 'boolean'],
            'status' =>                     ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'sku_id.required' =>                'El SKU es obligatorio.',
            'sku_id.exists' =>                  'El SKU no existe.',

            'line_id.required' =>               'La línea es obligatoria.',
            'line_id.exists' =>                 'La línea no existe.',

            'lbs_performance.required' =>       'El rendimiento en libras es obligatorio.',
            'lbs_performance.numeric' =>        'El rendimiento en libras debe ser un valor numérico.',

            'accepted_percentage.required' =>   'El porcentaje aceptado es obligatorio.',
            'accepted_percentage.numeric' =>    'El porcentaje aceptado debe ser un valor numérico.',

            'payment_method.required' =>        'El método de pago es obligatorio.',
            'payment_method.boolean' =>         'El método de pago debe ser un valor booleano.',

            'status.boolean' =>                 'El estado debe ser un valor booleano.',
        ];
    }
}
