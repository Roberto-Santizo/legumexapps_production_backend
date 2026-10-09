<?php

namespace App\Http\Requests\WeeklyPlanTaskLotRecords;

use App\Enums\CaptureType;
use App\Http\Requests\Shared\CaptureValueRules;
use App\Models\WeeklyPlanTask;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateWeeklyPlanTaskLotRecordRequest extends FormRequest
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
     * Rules of the fields assigned to the task line, see CaptureValueRules::forTask.
     *
     * @return array{rules: array<string, array<mixed>>, attributes: array<string, string>}
     */
    private function captureValueRules(): array
    {
        if ($this->captureValueRules === null) {
            $taskId = $this->input('weekly_plan_task_id');
            $task = is_numeric($taskId) ? WeeklyPlanTask::with('performance.line.lineFields.captureField')->find($taskId) : null;
            $this->captureValueRules = CaptureValueRules::forTask($task, CaptureType::Lot, false);
        }

        return $this->captureValueRules;
    }
}
