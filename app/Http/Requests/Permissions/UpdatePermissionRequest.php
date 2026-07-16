<?php

namespace App\Http\Requests\Permissions;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $role = $this->user()->role;
        if ($role != 'admin') {
            return false;
        }

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
            'name' => ['required', 'string', 'max:255', Rule::unique('permissions', 'name')->ignore($this->route('permission'), 'name')],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El permiso es requerido.',
            'name.string' => 'El permiso debe ser una cadena de texto.',
            'name.max' => 'El permiso no puede tener más de 255 caracteres.',
            'name.unique' => 'El permiso ya existe.',
        ];
    }
}
