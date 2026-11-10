<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\DefinesTaskArguments;
use App\Ai\Tools\Concerns\RespondsToAssistant;
use App\Http\Resources\WeeklyPlanTaskAssistant\WeeklyPlanTaskAssistantTaskResource;
use App\Interfaces\WeeklyPlanTaskAssistant\WeeklyPlanTaskAssistantServiceInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetWeeklyPlanTaskDetail implements Tool
{
    use DefinesTaskArguments, RespondsToAssistant;

    public function __construct(private WeeklyPlanTaskAssistantServiceInterface $service) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Detalle de una tarea del plan semanal: línea, SKU, status, capture_type (familia de captura: pallet, lot o product), libras planificadas y registradas, horas de tiempo muerto, '
            .'y conteos de empleados, tiempos muertos, observaciones, bitácora y tomas por familia. Úsala para saber qué tools de tomas aplican a la tarea.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->validate($this->taskIdRules(), $this->taskArgumentsMessages());

        return $this->attempt(function () use ($arguments) {
            $task = $this->service->getTaskDetail((string) $arguments['task_id']);

            return $this->json([
                'task' => (new WeeklyPlanTaskAssistantTaskResource($task))->resolve(),
                'counts' => [
                    'employees' => $task->employees_count,
                    'timeouts' => $task->timeouts_count,
                    'observations' => $task->observations_count,
                    'logs' => $task->logs_count,
                    'pallet_records' => $task->performance_records_count,
                    'lot_records' => $task->lot_records_count,
                    'product_records' => $task->product_records_count,
                ],
            ]);
        });
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $this->taskIdSchema($schema)->required(),
        ];
    }
}
