<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\RespondsToAssistant;
use App\Http\Resources\WeeklyPlanTaskAssistant\AssistantAudioSummaryResource;
use App\Interfaces\WeeklyPlanTaskAssistant\WeeklyPlanTaskAssistantServiceInterface;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GenerateAudioSummary implements Tool
{
    use RespondsToAssistant;

    public function __construct(
        private WeeklyPlanTaskAssistantServiceInterface $service,
        private string $conversationId,
        private User $user,
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Convierte a voz un guion de resumen y devuelve la URL pública del audio (audio_url). '
            .'Úsala solo cuando el usuario pida el resumen en audio, una sola vez por pedido y después de obtener los datos con las otras tools. '
            ."El guion va en español, en texto plano para oírse, con los números escritos como se dicen y máximo {$this->maxChars()} caracteres.";
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        try {
            $arguments = $request->validate([
                'script' => ['required', 'string', "max:{$this->maxChars()}"],
            ], [
                'script.required' => 'El guion del audio es obligatorio',
                'script.string' => 'El guion del audio debe ser texto',
                'script.max' => "El guion no puede superar {$this->maxChars()} caracteres",
            ]);
        } catch (ValidationException $exception) {
            return "Error: {$exception->validator->errors()->first()}";
        }

        return $this->attempt(fn () => $this->json(
            (new AssistantAudioSummaryResource(
                $this->service->generateAudioSummary($this->user, $this->conversationId, $arguments['script'])
            ))->resolve()
        ));
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'script' => $schema->string()->max($this->maxChars())->required()
                ->description('Guion en español y texto plano, sin markdown, tablas, listas, emojis ni URLs; números y unidades escritos como se dicen.'),
        ];
    }

    /**
     * Maximum length of the audio script.
     */
    private function maxChars(): int
    {
        return (int) config('services.weekly_plan_task_assistant.audio.max_chars');
    }
}
