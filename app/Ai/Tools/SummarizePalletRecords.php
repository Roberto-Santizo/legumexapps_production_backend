<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\DefinesTaskArguments;
use App\Ai\Tools\Concerns\RespondsToAssistant;
use App\Enums\CaptureType;
use App\Http\Resources\WeeklyPlanTaskAssistant\CaptureSummaryResource;
use App\Interfaces\WeeklyPlanTaskAssistant\WeeklyPlanTaskAssistantServiceInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SummarizePalletRecords implements Tool
{
    use DefinesTaskArguments, RespondsToAssistant;

    public function __construct(private WeeklyPlanTaskAssistantServiceInterface $service) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Resume las tomas por tarima (familia pallet) de una tarea (task_id) o de las tareas que cumplen los filtros, nunca ambos. '
            .'Devuelve libras planificadas, producidas (Σ neto) y % de avance; totales de cajas, litros, báscula, tara, neto, boleta y diferencial; '
            .'difference_pct = Σ diferencial / Σ boleta × 100; grupos por status del registro; y, con filtros, desglose por tarea (by_task). Usa siempre esta tool para totales y porcentajes.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $scope = $request->validate([
            ...$this->taskIdRules('nullable'),
            ...$this->taskFiltersRules(),
        ], $this->taskArgumentsMessages());

        return $this->attempt(fn () => $this->json(
            (new CaptureSummaryResource($this->service->summarizeCaptureRecords(CaptureType::Pallet, $scope)))->resolve()
        ));
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $this->taskIdSchema($schema)->nullable(),
            ...$this->taskFiltersSchema($schema),
        ];
    }
}
