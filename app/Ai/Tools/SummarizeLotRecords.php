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

class SummarizeLotRecords implements Tool
{
    use DefinesTaskArguments, RespondsToAssistant;

    public function __construct(private WeeklyPlanTaskAssistantServiceInterface $service) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Resume las tomas por lote (familia lot) de una tarea (task_id) o de las tareas que cumplen los filtros, nunca ambos. '
            .'Devuelve libras planificadas, producidas (Σ recortadas) y % de avance; totales de ingreso, MP aplicada, recortadas, sobremaduro y saldo GRN; '
            .'recovery_pct = Σ recortadas / Σ MP aplicada × 100 y overripe_pct = Σ sobremaduro / Σ MP aplicada × 100 (ponderados); grupos por lote; '
            .'y, con filtros, desglose por tarea (by_task). Usa siempre esta tool para totales y porcentajes.';
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
            (new CaptureSummaryResource($this->service->summarizeCaptureRecords(CaptureType::Lot, $scope)))->resolve()
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
