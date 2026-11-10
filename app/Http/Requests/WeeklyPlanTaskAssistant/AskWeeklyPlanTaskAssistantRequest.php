<?php

namespace App\Http\Requests\WeeklyPlanTaskAssistant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AskWeeklyPlanTaskAssistantRequest extends FormRequest
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
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'string', 'max:36'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'El mensaje es obligatorio',
            'message.string' => 'El mensaje debe ser texto',
            'message.max' => 'El mensaje no puede superar 2000 caracteres',
            'conversation_id.string' => 'El id de la conversación no es válido',
            'conversation_id.max' => 'El id de la conversación no es válido',
        ];
    }

    /**
     * The ask endpoint streams on success, so its errors keep the { statusCode, message, data } format.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'statusCode' => 422,
            'message' => $validator->errors()->first(),
            'data' => $validator->errors(),
        ], 422));
    }
}
