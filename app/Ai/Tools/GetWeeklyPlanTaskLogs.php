<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\DefinesTaskArguments;
use App\Ai\Tools\Concerns\RespondsToAssistant;
use App\Http\Resources\WeeklyPlanTaskAssistant\WeeklyPlanTaskLogResource;
use App\Interfaces\WeeklyPlanTaskAssistant\WeeklyPlanTaskAssistantServiceInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetWeeklyPlanTaskLogs implements Tool
{
    use DefinesTaskArguments, RespondsToAssistant;

    public function __construct(private WeeklyPlanTaskAssistantServiceInterface $service) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Bitácora de cambios de una tarea del plan semanal en orden cronológico: evento, campo, valor anterior, valor nuevo, usuario y fecha.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->validate($this->taskIdRules(), $this->taskArgumentsMessages());

        return $this->attempt(fn () => $this->json(
            WeeklyPlanTaskLogResource::collection($this->service->getTaskLogs((string) $arguments['task_id']))->resolve()
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
