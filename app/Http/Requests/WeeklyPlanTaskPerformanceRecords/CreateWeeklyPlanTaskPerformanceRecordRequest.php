<?php

namespace App\Http\Requests\WeeklyPlanTaskPerformanceRecords;

use App\Enums\CaptureType;
use App\Http\Requests\Shared\CaptureValueRules;
use App\Models\LineField;
use App\Models\WeeklyPlanTask;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;

class CreateWeeklyPlanTaskPerformanceRecordRequest extends FormRequest
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
            'weekly_plan_task_id' => ['required', 'integer', 'exists:weekly_plan_tasks,id'],
            ...$captureRules,
            'values' => ['required', 'array', ...($captureRules['values'] ?? [])],
        ];
    }

    public function messages(): array
    {
        return [
            'weekly_plan_task_id.required' => 'La tarea del plan semanal es obligatoria.',
            'weekly_plan_task_id.integer' => 'La tarea del plan semanal debe ser un número entero.',
            'weekly_plan_task_id.exists' => 'La tarea del plan semanal no existe.',

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
     * Rules of the fields assigned to the task line; empty when the task does not exist or its line has no pallet fields,
     * the service responds those cases.
     *
     * @return array{rules: array<string, array<mixed>>, attributes: array<string, string>}
     */
    private function captureValueRules(): array
    {
        return $this->captureValueRules ??= CaptureValueRules::forLineFields($this->palletLineFields(), false);
    }

    /**
     * @return Collection<int, LineField>
     */
    private function palletLineFields(): Collection
    {
        $taskId = $this->input('weekly_plan_task_id');
        $task = is_numeric($taskId) ? WeeklyPlanTask::with('performance.line.lineFields.captureField')->find($taskId) : null;
        $line = $task?->performance?->line;

        if ($line?->capture_type !== CaptureType::Pallet) {
            return collect();
        }

        return $line->lineFields;
    }
}
