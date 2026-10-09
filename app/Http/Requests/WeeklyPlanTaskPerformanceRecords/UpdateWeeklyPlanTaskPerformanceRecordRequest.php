<?php

namespace App\Http\Requests\WeeklyPlanTaskPerformanceRecords;

use App\Enums\CaptureType;
use App\Http\Requests\Shared\CaptureValueRules;
use App\Models\WeeklyPlanTaskPerformanceRecord;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWeeklyPlanTaskPerformanceRecordRequest extends FormRequest
{
    /**
     * @var array{rules: array<string, array<mixed>>, attributes: array<string, string>}|null
     */
    private ?array $captureValueRules = null;

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
        $captureRules = $this->captureValueRules()['rules'];

        return [
            ...$captureRules,
            'values' => ['required', 'array', ...($captureRules['values'] ?? [])],
        ];
    }

    public function messages(): array
    {
        return [
            'values.required' => 'Los valores de la captura son obligatorios.',
            'values.array' => 'Los valores de la captura deben ser un objeto.',

            ...CaptureValueRules::messages(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->captureValueRules()['attributes'];
    }

    /**
     * Rules of the fields assigned to the record task line, see CaptureValueRules::forTask.
     *
     * @return array{rules: array<string, array<mixed>>, attributes: array<string, string>}
     */
    private function captureValueRules(): array
    {
        if ($this->captureValueRules === null) {
            $recordId = $this->route('id');
            $record = is_numeric($recordId) ? WeeklyPlanTaskPerformanceRecord::with('task.performance.line.lineFields.captureField')->find($recordId) : null;
            $this->captureValueRules = CaptureValueRules::forTask($record?->task, CaptureType::Pallet, true);
        }

        return $this->captureValueRules;
    }
}
