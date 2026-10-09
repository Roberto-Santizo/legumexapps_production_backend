<?php

namespace App\Http\Requests\CaptureFields;

use App\Enums\CaptureFieldDataType;
use App\Models\CaptureField;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaptureFieldRequest extends FormRequest
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
            'key' => ['sometimes', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/'],
            'label' => ['sometimes', 'string', 'max:100'],
            'data_type' => ['sometimes', Rule::enum(CaptureFieldDataType::class)],
            'options' => [
                'required_if:data_type,select',
                Rule::prohibitedIf(fn () => ! $this->allowsOptions()),
                'array',
                'min:1',
            ],
            'options.*' => ['required', 'string', 'distinct', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.string' => 'La clave debe ser una cadena de texto.',
            'key.max' => 'La clave no debe exceder 50 caracteres.',
            'key.regex' => 'La clave debe estar en snake_case: iniciar con una letra minúscula y contener solo minúsculas, números y guiones bajos.',

            'label.string' => 'La etiqueta debe ser una cadena de texto.',
            'label.max' => 'La etiqueta no debe exceder 100 caracteres.',

            'data_type.enum' => 'El tipo de dato no es válido.',

            'options.required_if' => 'Las opciones son obligatorias para un campo de tipo selección.',
            'options.prohibited' => 'Las opciones solo se permiten en campos de tipo selección.',
            'options.array' => 'Las opciones deben ser un arreglo.',
            'options.min' => 'Debe indicar al menos una opción.',

            'options.*.required' => 'Cada opción es obligatoria.',
            'options.*.string' => 'Cada opción debe ser una cadena de texto.',
            'options.*.distinct' => 'Las opciones no se pueden repetir.',
            'options.*.max' => 'Cada opción no debe exceder 100 caracteres.',
        ];
    }

    /**
     * Options are allowed when the resulting data type (sent or current) is select.
     */
    private function allowsOptions(): bool
    {
        if ($this->has('data_type')) {
            return $this->input('data_type') === CaptureFieldDataType::Select->value;
        }

        $captureField = CaptureField::find($this->route('id'));

        return ! $captureField || $captureField->data_type === CaptureFieldDataType::Select;
    }
}
