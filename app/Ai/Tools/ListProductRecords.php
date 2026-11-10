<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\DefinesTaskArguments;
use App\Ai\Tools\Concerns\RespondsToAssistant;
use App\Enums\CaptureType;
use App\Http\Resources\WeeklyPlanTaskProductRecords\WeeklyPlanTaskProductRecordResource;
use App\Interfaces\WeeklyPlanTaskAssistant\WeeklyPlanTaskAssistantServiceInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListProductRecords implements Tool
{
    use DefinesTaskArguments, RespondsToAssistant;

    public function __construct(private WeeklyPlanTaskAssistantServiceInterface $service) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Lista las tomas por producto (familia product) de una tarea, fila por fila: materia prima, libras de materia prima, clasificadas, recortadas, rechazo, empacadas, '
            .'% recuperación (recortadas / materia prima) y % rechazo (rechazo / materia prima). Para totales o porcentajes usa SummarizeProductRecords.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->validate($this->taskIdRules(), $this->taskArgumentsMessages());

        return $this->attempt(fn () => $this->json(
            WeeklyPlanTaskProductRecordResource::collection(
                $this->service->getCaptureRecords((string) $arguments['task_id'], CaptureType::Product)
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
