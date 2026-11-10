<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\DefinesTaskArguments;
use App\Ai\Tools\Concerns\RespondsToAssistant;
use App\Enums\CaptureType;
use App\Http\Resources\WeeklyPlanTaskPerformanceRecords\WeeklyPlanTaskPerformanceRecordResource;
use App\Interfaces\WeeklyPlanTaskAssistant\WeeklyPlanTaskAssistantServiceInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListPalletRecords implements Tool
{
    use DefinesTaskArguments, RespondsToAssistant;

    public function __construct(private WeeklyPlanTaskAssistantServiceInterface $service) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Lista las tomas por tarima (familia pallet) de una tarea, fila por fila: número de tarima, lote, hora, cajas, litros, status, peso báscula, tara, '
            .'neto (báscula − tara), boleta (cajas × presentación) y diferencial (neto − boleta; positivo = sobra peso). Para totales o porcentajes usa SummarizePalletRecords.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->validate($this->taskIdRules(), $this->taskArgumentsMessages());

        return $this->attempt(fn () => $this->json(
            WeeklyPlanTaskPerformanceRecordResource::collection(
                $this->service->getCaptureRecords((string) $arguments['task_id'], CaptureType::Pallet)
            )->resolve()
        ));
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
