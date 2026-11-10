<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\DefinesTaskArguments;
use App\Ai\Tools\Concerns\RespondsToAssistant;
use App\Enums\CaptureType;
use App\Http\Resources\WeeklyPlanTaskLotRecords\WeeklyPlanTaskLotRecordResource;
use App\Interfaces\WeeklyPlanTaskAssistant\WeeklyPlanTaskAssistantServiceInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListLotRecords implements Tool
{
    use DefinesTaskArguments, RespondsToAssistant;

    public function __construct(private WeeklyPlanTaskAssistantServiceInterface $service) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Lista las tomas por lote (familia lot) de una tarea, fila por fila: fecha de ingreso, lote, hora, libras de ingreso, MP aplicada, recortadas, sobremaduro, '
            .'% recuperación (recortadas / MP aplicada), % sobremadurez (sobremaduro / MP aplicada) y saldo GRN (ingreso − MP aplicada). Para totales o porcentajes usa SummarizeLotRecords.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->validate($this->taskIdRules(), $this->taskArgumentsMessages());

        return $this->attempt(fn () => $this->json(
            WeeklyPlanTaskLotRecordResource::collection(
                $this->service->getCaptureRecords((string) $arguments['task_id'], CaptureType::Lot)
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
