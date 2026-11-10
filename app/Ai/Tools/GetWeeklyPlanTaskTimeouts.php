<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\DefinesTaskArguments;
use App\Ai\Tools\Concerns\RespondsToAssistant;
use App\Http\Resources\WeeklyPlanTaskTimeouts\WeeklyPlanTaskTimeoutResource;
use App\Interfaces\WeeklyPlanTaskAssistant\WeeklyPlanTaskAssistantServiceInterface;
use App\Models\WeeklyPlanTaskTimeout;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetWeeklyPlanTaskTimeouts implements Tool
{
    use DefinesTaskArguments, RespondsToAssistant;

    public function __construct(private WeeklyPlanTaskAssistantServiceInterface $service) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Tiempos muertos de una tarea del plan semanal: tipo, usuario, inicio, fin, duración en horas, observación y si sigue abierto; incluye timeout_hours con el total de horas.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->validate($this->taskIdRules(), $this->taskArgumentsMessages());

        return $this->attempt(function () use ($arguments) {
            $timeouts = $this->service->getTaskTimeouts((string) $arguments['task_id']);

            return $this->json([
                'timeouts' => WeeklyPlanTaskTimeoutResource::collection($timeouts)->resolve(),
                'timeout_hours' => round((float) $timeouts->sum(fn (WeeklyPlanTaskTimeout $timeout) => (float) $timeout->duration_hours), 2),
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
