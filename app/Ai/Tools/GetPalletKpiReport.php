<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\DefinesTaskArguments;
use App\Ai\Tools\Concerns\RespondsToAssistant;
use App\Http\Resources\WeeklyPlanTaskAssistant\PalletKpiReportResource;
use App\Interfaces\WeeklyPlanTaskAssistant\WeeklyPlanTaskAssistantServiceInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetPalletKpiReport implements Tool
{
    use DefinesTaskArguments, RespondsToAssistant;

    public function __construct(private WeeklyPlanTaskAssistantServiceInterface $service) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Calcula el formato KPI de las líneas por tarima (familia pallet) de una tarea (task_id) o de las tareas que cumplen los filtros, nunca ambos. '
            .'Úsala siempre para KPI, resumen, rendimiento, producción, desecho o sobredosificación de líneas pallet. '
            .'Devuelve rows (una fila por tarea con tomas: cajas, tarimas y libras producidas, libras y % de desecho, libras y % de sobredosificación y sobredosificación por tarima), '
            .'totals (totales ya ponderados; null con menos de dos filas), tasks_without_records (tareas del alcance sin tomas, que no salen en rows) '
            .'y pallets_source por fila (formula, o count si las tarimas se contaron porque el SKU no tiene cajas por tarima). '
            .'Los porcentajes van en escala 0-100 y null significa sin dato, no 0.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $scope = $request->validate([
            ...$this->taskIdRules('nullable'),
            ...$this->taskFiltersRules(),
            'line_name' => ['nullable', 'string'],
        ], [
            ...$this->taskArgumentsMessages(),
            'line_name.string' => 'El nombre de línea debe ser texto',
        ]);

        return $this->attempt(fn () => $this->json(
            (new PalletKpiReportResource($this->service->getPalletKpiReport($scope)))->resolve()
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
            'line_name' => $schema->string()->nullable()->description('Nombre de la línea o parte de él, sin distinguir mayúsculas (ej. "Reempaque 5" trae también "REEMPAQUE 5 PM"). Solo refina; no basta por sí solo.'),
        ];
    }
}
