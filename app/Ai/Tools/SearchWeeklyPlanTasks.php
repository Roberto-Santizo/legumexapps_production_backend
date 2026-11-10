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

class SearchWeeklyPlanTasks implements Tool
{
    use DefinesTaskArguments, RespondsToAssistant;

    public function __construct(private WeeklyPlanTaskAssistantServiceInterface $service) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Busca tareas del plan semanal. Exige al menos uno de: weekly_plan_id, week + year, u operation_date_from + operation_date_to. '
            .'line_code, sku_code y status solo refinan. Devuelve cada tarea con su línea, SKU, status, capture_type (familia de captura), libras planificadas y registradas, semana y año.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $filters = $request->validate([
            ...$this->taskFiltersRules(),
            'status' => ['nullable', 'integer', 'between:1,5'],
        ], $this->taskArgumentsMessages());

        return $this->attempt(fn () => $this->json(
            WeeklyPlanTaskAssistantTaskResource::collection($this->service->searchTasks($filters))->resolve()
        ));
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->taskFiltersSchema($schema),
            'status' => $schema->integer()->nullable()->min(1)->max(5)->description('Status de la tarea: 1 Pendiente Entrega Material de Empaque, 2 Lista para confirmación de asignaciones, 3 Lista para ejecución, 4 En Progreso, 5 Finalizada.'),
        ];
    }
}
