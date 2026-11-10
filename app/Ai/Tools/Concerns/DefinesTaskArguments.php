<?php

namespace App\Ai\Tools\Concerns;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

trait DefinesTaskArguments
{
    /**
     * Schema of the task id argument.
     */
    protected function taskIdSchema(JsonSchema $schema): Type
    {
        return $schema->integer()->description('Id de la tarea del plan semanal.');
    }

    /**
     * Schema of the filters that narrow the weekly plan tasks.
     *
     * @return array<string, Type>
     */
    protected function taskFiltersSchema(JsonSchema $schema): array
    {
        $maxRangeDays = (int) config('services.weekly_plan_task_assistant.max_range_days');

        return [
            'weekly_plan_id' => $schema->integer()->nullable()->description('Id del plan semanal.'),
            'week' => $schema->integer()->nullable()->min(1)->max(53)->description('Número de semana del plan (1-53). Requiere year.'),
            'year' => $schema->integer()->nullable()->description('Año del plan semanal. Requiere week.'),
            'operation_date_from' => $schema->string()->nullable()->format('date')->description('Fecha de operación inicial (Y-m-d). Requiere operation_date_to.'),
            'operation_date_to' => $schema->string()->nullable()->format('date')->description("Fecha de operación final (Y-m-d), máximo {$maxRangeDays} días después de operation_date_from."),
            'line_code' => $schema->string()->nullable()->description('Código exacto de la línea. Solo refina; no basta por sí solo.'),
            'sku_code' => $schema->string()->nullable()->description('Código del SKU o parte de él. Solo refina; no basta por sí solo.'),
        ];
    }

    /**
     * Validation rules of the task id argument.
     *
     * @return array<string, array<int, string>>
     */
    protected function taskIdRules(string $presence = 'required'): array
    {
        return [
            'task_id' => [$presence, 'integer'],
        ];
    }

    /**
     * Validation rules of the task filters; the scope and the date range are validated by the Service.
     *
     * @return array<string, array<int, string>>
     */
    protected function taskFiltersRules(): array
    {
        return [
            'weekly_plan_id' => ['nullable', 'integer'],
            'week' => ['nullable', 'integer', 'between:1,53', 'required_with:year'],
            'year' => ['nullable', 'integer', 'required_with:week'],
            'operation_date_from' => ['nullable', 'date_format:Y-m-d', 'required_with:operation_date_to'],
            'operation_date_to' => ['nullable', 'date_format:Y-m-d', 'required_with:operation_date_from', 'after_or_equal:operation_date_from'],
            'line_code' => ['nullable', 'string'],
            'sku_code' => ['nullable', 'string'],
        ];
    }

    /**
     * Validation messages of the task arguments, in Spanish so the model can relay them.
     *
     * @return array<string, string>
     */
    protected function taskArgumentsMessages(): array
    {
        return [
            'task_id.required' => 'El id de la tarea es obligatorio',
            'task_id.integer' => 'El id de la tarea debe ser un número entero',
            'weekly_plan_id.integer' => 'El id del plan semanal debe ser un número entero',
            'week.integer' => 'La semana debe ser un número entero',
            'week.between' => 'La semana debe estar entre 1 y 53',
            'week.required_with' => 'La semana es obligatoria cuando se indica el año',
            'year.integer' => 'El año debe ser un número entero',
            'year.required_with' => 'El año es obligatorio cuando se indica la semana',
            'operation_date_from.date_format' => 'La fecha inicial debe tener el formato Y-m-d',
            'operation_date_from.required_with' => 'La fecha inicial es obligatoria cuando se indica la fecha final',
            'operation_date_to.date_format' => 'La fecha final debe tener el formato Y-m-d',
            'operation_date_to.required_with' => 'La fecha final es obligatoria cuando se indica la fecha inicial',
            'operation_date_to.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial',
            'line_code.string' => 'El código de línea debe ser texto',
            'sku_code.string' => 'El código de SKU debe ser texto',
            'status.integer' => 'El status debe ser un número entero',
            'status.between' => 'El status debe estar entre 1 y 5',
        ];
    }
}
