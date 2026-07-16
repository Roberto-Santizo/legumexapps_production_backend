<?php

namespace App\Http\Requests\Users;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddPermissionToUserRequest extends FormRequest
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
            'user_id' => ['required', 'numeric', 'exists:users,id'],
            'permission_id' => ['required', 'numeric', 'exists:permissions,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'El usuario es obligatorio.',
            'user_id.numeric' => 'El identificador del usuario debe ser un número.',
            'user_id.exists' => 'El usuario seleccionado no existe.',

            'permission_id.required' => 'El permiso es obligatorio.',
            'permission_id.numeric' => 'El identificador del permiso debe ser un número.',
            'permission_id.exists' => 'El permiso seleccionado no existe.',
        ];
    }
}
