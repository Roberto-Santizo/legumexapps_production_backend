<?php

namespace App\Http\Requests\Shared;

use App\Enums\CaptureFieldDataType;
use App\Models\LineField;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class CaptureValueRules
{
    /**
     * Build the validation rules of the "values" object from the fields assigned to a line.
     *
     * @param  Collection<int, LineField>  $lineFields  with captureField loaded
     * @return array{rules: array<string, array<mixed>>, attributes: array<string, string>}
     */
    public static function forLineFields(Collection $lineFields, bool $isUpdate): array
    {
        $rules = ['values' => [self::allowedKeysRule($lineFields)]];
        $attributes = [];

        foreach ($lineFields as $lineField) {
            $captureField = $lineField->captureField;

            if ($captureField->is_calculated) {
                continue;
            }

            $attribute = "values.{$captureField->key}";

            $rules[$attribute] = [
                ...($isUpdate ? ['sometimes'] : []),
                $lineField->is_required ? 'required' : 'nullable',
                ...self::typeRules($lineField),
            ];

            $attributes[$attribute] = self::label($lineField);
        }

        return ['rules' => $rules, 'attributes' => $attributes];
    }

    /**
     * Spanish messages of the rules applied to the "values" object.
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'values.*.required' => 'El campo :attribute es obligatorio.',
            'values.*.numeric' => 'El campo :attribute debe ser un valor numérico.',
            'values.*.integer' => 'El campo :attribute debe ser un número entero.',
            'values.*.string' => 'El campo :attribute debe ser una cadena de texto.',
            'values.*.max' => 'El campo :attribute no debe exceder :max caracteres.',
            'values.*.min' => 'El campo :attribute debe ser mayor o igual a :min.',
            'values.*.date_format' => 'El campo :attribute debe tener el formato :format.',
            'values.*.boolean' => 'El campo :attribute debe ser verdadero o falso.',
            'values.*.in' => 'El campo :attribute no es una opción válida.',
        ];
    }

    /**
     * Rules of the field data type; system numeric fields can not be negative and some system keys have extra limits.
     *
     * @return array<int, mixed>
     */
    private static function typeRules(LineField $lineField): array
    {
        $captureField = $lineField->captureField;

        $rules = match ($captureField->data_type) {
            CaptureFieldDataType::Number => ['numeric'],
            CaptureFieldDataType::Integer => ['integer'],
            CaptureFieldDataType::Text => ['string', 'max:500'],
            CaptureFieldDataType::Date => ['date_format:Y-m-d'],
            CaptureFieldDataType::Time => ['date_format:H:i'],
            CaptureFieldDataType::Boolean => ['boolean'],
            CaptureFieldDataType::Select => [Rule::in($captureField->options ?? [])],
        };

        if (! $captureField->is_system) {
            return $rules;
        }

        return match (true) {
            $captureField->key === 'pallet_number' => [...$rules, 'min:1'],
            $captureField->key === 'lot' => ['string', 'max:50'],
            in_array($captureField->data_type, [CaptureFieldDataType::Number, CaptureFieldDataType::Integer], true) => [...$rules, 'min:0'],
            default => $rules,
        };
    }

    /**
     * Only the keys assigned to the line can be sent, and calculated fields are resolved by the backend.
     *
     * @param  Collection<int, LineField>  $lineFields
     */
    private static function allowedKeysRule(Collection $lineFields): Closure
    {
        $lineFieldsByKey = $lineFields->keyBy(fn (LineField $lineField) => $lineField->captureField->key);

        return function (string $attribute, mixed $value, Closure $fail) use ($lineFieldsByKey): void {
            if (! is_array($value)) {
                return;
            }

            foreach (array_keys($value) as $key) {
                $lineField = $lineFieldsByKey->get($key);

                if (! $lineField) {
                    $fail("El campo {$key} no está configurado para la línea");
                } elseif ($lineField->captureField->is_calculated) {
                    $fail('El campo '.self::label($lineField).' es calculado y no se puede enviar');
                }
            }
        };
    }

    private static function label(LineField $lineField): string
    {
        return $lineField->label ?: $lineField->captureField->label;
    }
}
