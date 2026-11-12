<?php

namespace Database\Seeders;

use App\Enums\CaptureType;
use App\Models\Line;
use App\Models\LineSku;
use App\Models\RawMaterial;
use App\Models\Sku;
use App\Models\WeeklyPlan;
use App\Models\WeeklyPlanTask;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Demo data to exercise the WeeklyPlanTaskAssistant. Takes the lines of the weekly plan tasks already loaded
 * and creates 3–4 tasks per line per week, from two weeks ago up to the current week, with SKUs different from
 * the original ones and varied measures and performances per capture family:
 *  - pallet: weight differentials, mostly negative.
 *  - product: recovery percentages, several below 40%.
 *  - lot: recovery and overripe percentages.
 *
 * Run: php artisan db:seed --class=WeeklyPlanTaskAssistantDemoSeeder
 */
class WeeklyPlanTaskAssistantDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    private const RANDOM_SEED = 46;

    private const WEEKS_BACK = 2;

    private const USER_ID = 1;

    /**
     * Pounds per box assumed to size product/lot tasks whose SKU has no presentation.
     */
    private const NOMINAL_PRESENTATION = 25;

    private const DESTINATIONS = ['MIAMI', 'LOS ANGELES', 'HOUSTON', 'NEW JERSEY', 'PHILADELPHIA', 'SAVANNAH'];

    /**
     * Columns of each record table that come from capture fields; the ones not assigned to the line are left null.
     *
     * @var array<string, list<string>>
     */
    private const CAPTURE_COLUMNS = [
        'pallet' => ['pallet_number', 'lot', 'recorded_at', 'boxes', 'liters', 'status', 'ticket_weight', 'scale_weight', 'tare', 'net_weight', 'difference', 'observations'],
        'lot' => ['entry_date', 'lot', 'recorded_at', 'intake_lbs', 'applied_raw_lbs', 'trimmed_lbs', 'overripe_lbs', 'recovery_pct', 'overripe_pct', 'grn_balance', 'observations'],
        'product' => ['raw_material', 'raw_lbs', 'classified_lbs', 'trimmed_lbs', 'rejected_lbs', 'packed_lbs', 'recovery_pct', 'rejection_pct', 'observations'],
    ];

    private const RECORD_TABLES = [
        'pallet' => 'weekly_plan_task_performance_records',
        'lot' => 'weekly_plan_task_lot_records',
        'product' => 'weekly_plan_task_product_records',
    ];

    /**
     * Performance profiles per family: label, severity, main range (% differential or % recovery),
     * secondary range (% overripe, lot only) and weight.
     *
     * @var array<string, list<array{label: string, severity: string, range: array{0: float, 1: float}, secondary?: array{0: float, 1: float}, weight: int}>>
     */
    private const PROFILES = [
        'pallet' => [
            ['label' => 'sobrepeso', 'severity' => 'warning', 'range' => [0.10, 0.60], 'weight' => 12],
            ['label' => 'exacto', 'severity' => 'ok', 'range' => [-0.10, 0.10], 'weight' => 10],
            ['label' => 'faltante_leve', 'severity' => 'ok', 'range' => [-1.20, -0.30], 'weight' => 33],
            ['label' => 'faltante_moderado', 'severity' => 'moderate', 'range' => [-2.50, -1.20], 'weight' => 27],
            ['label' => 'faltante_critico', 'severity' => 'critical', 'range' => [-4.50, -2.50], 'weight' => 18],
        ],
        'product' => [
            ['label' => 'recuperacion_critica', 'severity' => 'critical', 'range' => [22.0, 39.5], 'weight' => 30],
            ['label' => 'recuperacion_baja', 'severity' => 'moderate', 'range' => [40.0, 55.0], 'weight' => 25],
            ['label' => 'recuperacion_normal', 'severity' => 'ok', 'range' => [55.0, 72.0], 'weight' => 30],
            ['label' => 'recuperacion_alta', 'severity' => 'ok', 'range' => [72.0, 88.0], 'weight' => 15],
        ],
        'lot' => [
            ['label' => 'lote_critico', 'severity' => 'critical', 'range' => [28.0, 45.0], 'secondary' => [12.0, 22.0], 'weight' => 25],
            ['label' => 'lote_bajo', 'severity' => 'moderate', 'range' => [45.0, 60.0], 'secondary' => [7.0, 12.0], 'weight' => 25],
            ['label' => 'lote_normal', 'severity' => 'ok', 'range' => [60.0, 75.0], 'secondary' => [3.0, 7.0], 'weight' => 35],
            ['label' => 'lote_excelente', 'severity' => 'ok', 'range' => [75.0, 88.0], 'secondary' => [0.5, 3.0], 'weight' => 15],
        ],
    ];

    /**
     * Task observations per family and severity; "ok" tasks get a neutral note now and then.
     *
     * @var array<string, array<string, list<string>>>
     */
    private const TASK_OBSERVATIONS = [
        'pallet' => [
            'critical' => ['Faltante de peso considerable en varias tarimas, se solicitó recalibración de báscula.', 'Producto con exceso de escarcha, se reclasificó parte del lote.'],
            'moderate' => ['Se detectó variación en el llenado de cajas, se ajustó la dosificadora.'],
            'warning' => ['Llenado por encima del peso de boleta, revisar regulación.'],
        ],
        'product' => [
            'critical' => ['Recuperación muy baja: materia prima con daño mecánico y exceso de tallo.', 'Se reportó a calidad de campo la condición de la materia prima recibida.'],
            'moderate' => ['Recuperación por debajo del estándar, se reforzó la capacitación de recorte.'],
        ],
        'lot' => [
            'critical' => ['Lotes con alta sobremadurez, se solicitó revisión del tiempo de cosecha al productor.', 'Recuperación baja por producto sobremaduro, se separó rechazo para merma.'],
            'moderate' => ['Sobremadurez por encima de lo esperado en algunos lotes.'],
        ],
    ];

    /**
     * Timeout catalog ids that plausibly stop each kind of line.
     *
     * @var array<string, list<int>>
     */
    private const TIMEOUT_IDS = [
        'pallet' => [9, 11, 14, 19, 22, 25, 34],
        'lot' => [3, 9, 11, 12, 14, 19, 25, 27],
        'product' => [3, 9, 11, 12, 14, 19, 25, 27],
    ];

    private const FIRST_NAMES = ['ANA', 'MARIA', 'JOSE', 'LUIS', 'SANDRA', 'CARLOS', 'ROSA', 'JUAN', 'MIRNA', 'PEDRO', 'GLORIA', 'MARVIN', 'ELENA', 'OSCAR', 'LESBIA', 'BYRON', 'DORA', 'EDGAR', 'SILVIA', 'HUGO'];

    private const LAST_NAMES = ['LOPEZ', 'GARCIA', 'PEREZ', 'XOCOP', 'CHAVEZ', 'TOMAS', 'AJCOT', 'MORALES', 'COJTI', 'RAMIREZ', 'SICAJAN', 'HERNANDEZ', 'CUMES', 'ORTIZ', 'SIRIN', 'MENDEZ'];

    private CarbonImmutable $now;

    private int $employeeSequence = 30000;

    /**
     * @var array<int, list<array{code: string, name: string, position: string}>>
     */
    private array $crews = [];

    /**
     * Capture field keys assigned to each line.
     *
     * @var array<int, list<string>>
     */
    private array $assignedKeys = [];

    /**
     * @var array<int, list<string>>
     */
    private array $rawMaterialNames = [];

    /**
     * @var array<string, int>
     */
    private array $summary = ['tasks' => 0, 'pallet_records' => 0, 'lot_records' => 0, 'product_records' => 0, 'employees' => 0, 'timeouts' => 0, 'observations' => 0];

    public function run(): void
    {
        mt_srand(self::RANDOM_SEED);
        $this->now = CarbonImmutable::now();

        $baseTasks = WeeklyPlanTask::with('performance.line.lineFields.captureField')->get();
        $lines = $baseTasks->map(fn (WeeklyPlanTask $task) => $task->performance?->line)->filter()->unique('id')->sortBy('name')->values();
        $baseLineSkuIds = $baseTasks->pluck('line_sku_id')->unique()->all();

        $currentMonday = $this->now->startOfWeek();
        $mondays = collect(range(self::WEEKS_BACK, 0))->map(fn (int $weeksBack) => $currentMonday->subWeeks($weeksBack));

        $this->ensureNotSeeded($mondays->first());

        DB::transaction(function () use ($lines, $mondays, $baseLineSkuIds): void {
            foreach ($lines as $line) {
                $lineSkus = $this->candidateLineSkus($line, $baseLineSkuIds);

                if ($lineSkus->isEmpty()) {
                    $this->command?->warn("{$line->name}: sin SKUs distintos a los de las tareas originales, se omite.");

                    continue;
                }

                $this->crews[$line->id] = $this->buildCrew($line);
                $this->assignedKeys[$line->id] = $line->lineFields->map(fn ($lineField) => $lineField->captureField?->key)->filter()->values()->all();
                $queue = collect($lineSkus->all());

                foreach ($mondays as $monday) {
                    $weeklyPlan = WeeklyPlan::firstOrCreate(['week' => $monday->isoWeek(), 'year' => $monday->isoWeekYear()]);

                    foreach ($this->operationDates($monday) as $operationDate) {
                        if ($queue->isEmpty()) {
                            $queue = collect($lineSkus->all());
                        }

                        $this->createTask($weeklyPlan, $line, $queue->shift(), $operationDate);
                    }
                }

                $this->command?->info("{$line->name} ({$line->capture_type->value}): {$lineSkus->count()} SKUs candidatos");
            }
        });

        $this->command?->info('Datos demo creados: '.json_encode($this->summary));
    }

    /**
     * Abort when the oldest demo week already has tasks, so the seeder is not applied twice.
     */
    private function ensureNotSeeded(CarbonImmutable $oldestMonday): void
    {
        $alreadySeeded = WeeklyPlan::where('week', $oldestMonday->isoWeek())
            ->where('year', $oldestMonday->isoWeekYear())
            ->whereHas('tasks')
            ->exists();

        if ($alreadySeeded) {
            throw new RuntimeException("La semana {$oldestMonday->isoWeek()} ya tiene tareas; el seeder demo ya fue ejecutado.");
        }
    }

    /**
     * Active line SKUs other than the ones of the original tasks, shuffled. Pallet lines need a presentation to weigh
     * pallets; on the other lines SKUs with presentation go first.
     *
     * @param  list<int>  $baseLineSkuIds
     * @return Collection<int, LineSku>
     */
    private function candidateLineSkus(Line $line, array $baseLineSkuIds): Collection
    {
        $lineSkus = LineSku::with('sku')
            ->where('line_id', $line->id)
            ->where('status', true)
            ->where('lbs_performance', '>', 0)
            ->whereNotIn('id', $baseLineSkuIds)
            ->orderBy('id')
            ->get();

        [$withPresentation, $withoutPresentation] = $lineSkus->partition(fn (LineSku $lineSku) => $lineSku->sku->presentation > 0);

        if ($line->capture_type === CaptureType::Pallet) {
            [$withBoxesPerPallet, $withoutBoxesPerPallet] = $withPresentation->partition(fn (LineSku $lineSku) => $lineSku->sku->boxes_per_pallet > 0);

            return $this->shuffle($withBoxesPerPallet)->concat($this->shuffle($withoutBoxesPerPallet))->values();
        }

        return $this->shuffle($withPresentation)->concat($this->shuffle($withoutPresentation))->values();
    }

    /**
     * 3 or 4 operation days (Mon–Sat) per week. The current week always includes Monday (finished) and today (in progress).
     *
     * @return list<CarbonImmutable>
     */
    private function operationDates(CarbonImmutable $monday): array
    {
        $taskCount = mt_rand(3, 4);
        $offsets = $this->shuffle(collect(range(0, 5)))->all();

        if ($monday->isSameDay($this->now->startOfWeek())) {
            $todayOffset = (int) $monday->diffInDays($this->now->startOfDay());
            $forced = array_values(array_unique([0, min($todayOffset, 5)]));
            $offsets = array_merge($forced, array_diff($offsets, $forced));
        }

        $selected = array_slice($offsets, 0, $taskCount);
        sort($selected);

        return array_map(fn (int $offset) => $monday->addDays($offset), $selected);
    }

    /**
     * Plan the task the way WeeklyPlanTasksService does (pallets and hours are 0 when the SKU lacks the data) and
     * simulate its execution according to its status.
     */
    private function createTask(WeeklyPlan $weeklyPlan, Line $line, LineSku $lineSku, CarbonImmutable $operationDate): void
    {
        $sku = $lineSku->sku;
        $captureType = $line->capture_type;
        $presentation = (float) $sku->presentation;
        $effectivePresentation = $presentation > 0 ? $presentation : self::NOMINAL_PRESENTATION;
        $boxesPerPallet = (int) $sku->boxes_per_pallet;
        $effectiveBoxesPerPallet = $boxesPerPallet ?: max(20, (int) round(2000 / $effectivePresentation));
        $lbsPerformance = (float) $lineSku->lbs_performance;
        $maxShiftPounds = 11 * $lbsPerformance;

        if ($captureType === CaptureType::Pallet) {
            $maxPallets = max(4, (int) floor($maxShiftPounds / ($effectiveBoxesPerPallet * $effectivePresentation)));
            $boxes = mt_rand(min(6, $maxPallets), min(40, $maxPallets)) * $effectiveBoxesPerPallet;
        } else {
            $targetPounds = $this->randomFloat(min(3000, $maxShiftPounds * 0.4), min(40000, $maxShiftPounds));
            $boxes = max(1, (int) round($targetPounds / $effectivePresentation));
        }

        $status = $this->statusFor($operationDate);
        $plannedAt = $operationDate->startOfWeek()->subDays(2)->setTime(15, 0);

        $taskId = DB::table('weekly_plan_tasks')->insertGetId([
            'boxes' => $boxes,
            'pallets' => $boxesPerPallet ? $boxes / $boxesPerPallet : 0,
            'hours' => $presentation > 0 ? round($boxes * $presentation / $lbsPerformance, 2) : 0,
            'destination' => self::DESTINATIONS[mt_rand(0, count(self::DESTINATIONS) - 1)],
            'operation_date' => $operationDate->startOfDay(),
            'weekly_plan_id' => $weeklyPlan->id,
            'line_sku_id' => $lineSku->id,
            'status' => $status,
            'created_at' => $plannedAt,
            'updated_at' => $plannedAt,
        ]);
        $this->summary['tasks']++;

        if ($status >= 3) {
            $this->assignEmployees($taskId, $line);
        }

        if ($status >= 4) {
            $this->runTask([
                'id' => $taskId,
                'line' => $line,
                'sku' => $sku,
                'presentation' => $effectivePresentation,
                'boxesPerPallet' => $boxesPerPallet,
                'effectiveBoxesPerPallet' => $effectiveBoxesPerPallet,
                'plannedPounds' => $boxes * $effectivePresentation,
                'plannedHours' => $boxes * $effectivePresentation / $lbsPerformance,
                'operationDate' => $operationDate,
                'finished' => $status === 5,
            ]);
        }
    }

    /**
     * Past days are finished, today is in progress, upcoming days go from ready for execution to pending material.
     */
    private function statusFor(CarbonImmutable $operationDate): int
    {
        $today = $this->now->startOfDay();

        if ($operationDate->lt($today)) {
            return 5;
        }

        if ($operationDate->isSameDay($today)) {
            return 4;
        }

        return match ((int) $today->diffInDays($operationDate)) {
            1 => 3,
            2 => 2,
            default => 1,
        };
    }

    /**
     * Simulate the execution: capture records with a performance profile, timeouts, observations and closing values.
     *
     * @param  array{id: int, line: Line, sku: Sku, presentation: float, boxesPerPallet: int, effectiveBoxesPerPallet: int, plannedPounds: float, plannedHours: float, operationDate: CarbonImmutable, finished: bool}  $task
     */
    private function runTask(array $task): void
    {
        $family = $task['line']->capture_type->value;
        $profile = $this->pickProfile($family);
        $efficiency = $this->randomFloat(0.82, 1.08);
        $start = $task['operationDate']->setTime(7, mt_rand(0, 25));

        if (! $task['finished'] && $start->gt($this->now->subHours(2))) {
            $start = $this->now->subHours(3);
        }

        $timeouts = $this->buildTimeouts($family, $start, $task['plannedHours'], $profile['severity'], $task['finished']);
        $timeoutHours = array_sum(array_column($timeouts, 'duration_hours'));
        $workHours = $task['plannedHours'] / $efficiency;

        if ($task['finished']) {
            $completion = mt_rand(1, 100) <= 70 ? 1.0 : $this->randomFloat(0.80, 0.97);
            $end = $start->addMinutes((int) round(($workHours * $completion + $timeoutHours) * 60));
        } else {
            $elapsedHours = min($workHours * 0.6, $start->diffInMinutes($this->now) / 60);
            $completion = max(0.1, min(0.6, $elapsedHours / $workHours));
            $end = $start->addMinutes((int) round($elapsedHours * 60));
        }

        $targetPounds = $task['plannedPounds'] * $completion;

        $records = match ($family) {
            'pallet' => $this->buildPalletRecords($task, $targetPounds, $profile, $start, $end),
            'product' => $this->buildProductRecords($task, $targetPounds, $profile, $start, $end),
            'lot' => $this->buildLotRecords($task, $targetPounds, $profile, $start, $end),
        };

        $records = array_map(fn (array $record) => $this->keepAssignedColumns($record, $task['line'], $family), $records);

        DB::table(self::RECORD_TABLES[$family])->insert($records);
        $this->summary["{$family}_records"] += count($records);

        foreach ($timeouts as $timeout) {
            DB::table('weekly_plan_task_timeouts')->insert([...$timeout, 'weekly_plan_task_id' => $task['id'], 'created_at' => $timeout['start_date'], 'updated_at' => $timeout['end_date'] ?? $timeout['start_date']]);
        }
        $this->summary['timeouts'] += count($timeouts);

        $this->addObservations($task['id'], $family, $profile['severity'], $end);

        if ($family === 'pallet') {
            $producedBoxes = (int) array_sum(array_column($records, 'boxes'));
            $producedPounds = array_sum(array_column($records, 'net_weight'));
        } else {
            $producedPounds = array_sum(array_map(fn (array $record) => $record['packed_lbs'] ?? $record['trimmed_lbs'], $records));
            $producedBoxes = (int) round($producedPounds / $task['presentation']);
        }

        DB::table('weekly_plan_tasks')->where('id', $task['id'])->update([
            'start_date' => $start,
            'end_date' => $task['finished'] ? $end : null,
            'produced_boxes' => $task['finished'] ? $producedBoxes : null,
            'produced_pallets' => $task['finished'] ? ($task['boxesPerPallet'] ? round($producedBoxes / $task['boxesPerPallet'], 2) : 0) : null,
            'weighed_pounds' => $task['finished'] ? round($producedPounds, 2) : null,
            'updated_at' => $end,
        ]);
    }

    /**
     * Pallet records around the profile differential (% of ticket weight).
     *
     * @param  array<string, mixed>  $task
     * @param  array<string, mixed>  $profile
     * @return list<array<string, mixed>>
     */
    private function buildPalletRecords(array $task, float $targetPounds, array $profile, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $boxesPerPallet = $task['effectiveBoxesPerPallet'];
        $palletsTarget = $targetPounds / ($boxesPerPallet * $task['presentation']);
        $fullPallets = (int) floor($palletsTarget);
        $lastPalletBoxes = (int) round(($palletsTarget - $fullPallets) * $boxesPerPallet);
        $palletBoxes = array_fill(0, $fullPallets, $boxesPerPallet);

        if ($lastPalletBoxes > 0 || $palletBoxes === []) {
            $palletBoxes[] = max(1, $lastPalletBoxes);
        }

        $lots = $this->lotCodes($task['line'], $task['operationDate'], 'PA', mt_rand(1, 100) <= 35 ? 2 : 1);
        $count = count($palletBoxes);
        $timestamps = $this->spread($start, $end, $count);
        $records = [];

        foreach ($palletBoxes as $index => $boxes) {
            $ticketWeight = round($boxes * $task['presentation'], 2);
            $pct = $profile['value'] + $this->gaussian() * 0.35;
            $netWeight = round($ticketWeight * (1 + $pct / 100));
            $tare = mt_rand(175, 192);
            $difference = round($netWeight - $ticketWeight, 2);
            $isShort = $difference < -0.03 * $ticketWeight;

            $records[] = [
                'weekly_plan_task_id' => $task['id'],
                'user_id' => self::USER_ID,
                'pallet_number' => $index + 1,
                'boxes' => $boxes,
                'net_weight' => $netWeight,
                'ticket_weight' => $ticketWeight,
                'difference' => $difference,
                'lot' => $lots[$index < $count / 2 ? 0 : count($lots) - 1],
                'recorded_at' => $timestamps[$index]->format('H:i:s'),
                'liters' => round($netWeight / 1.04, 2),
                'status' => $isShort ? 'RETENIDO' : 'LIBERADO',
                'scale_weight' => $netWeight + $tare,
                'tare' => $tare,
                'observations' => $isShort ? 'Tarima con faltante de peso, se reportó a calidad' : null,
                'extra_values' => null,
                'created_at' => $timestamps[$index],
                'updated_at' => $timestamps[$index],
            ];
        }

        return $records;
    }

    /**
     * Product records by raw material batch: trimmed pounds reach the target with the profile recovery.
     *
     * @param  array<string, mixed>  $task
     * @param  array<string, mixed>  $profile
     * @return list<array<string, mixed>>
     */
    private function buildProductRecords(array $task, float $targetPounds, array $profile, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rawTotal = $targetPounds / ($profile['value'] / 100);
        $shares = $this->splitShares((int) max(2, min(10, round($rawTotal / 1500))));
        $materials = $this->rawMaterialNamesFor($task['sku']);
        $timestamps = $this->spread($start, $end, count($shares));
        $records = [];

        foreach ($shares as $index => $share) {
            $rawLbs = round($rawTotal * $share, 2);
            $recoveryPct = $this->clamp($profile['value'] + $this->gaussian() * 3, 5, 97);
            $trimmedLbs = round($rawLbs * $recoveryPct / 100, 2);
            $rejectedLbs = round(($rawLbs - $trimmedLbs) * $this->randomFloat(0.35, 0.85), 2);
            $classifiedLbs = round($trimmedLbs + ($rawLbs - $trimmedLbs - $rejectedLbs) * $this->randomFloat(0.3, 0.9), 2);
            $packedLbs = round($trimmedLbs * $this->randomFloat(0.93, 1.0), 2);

            $records[] = [
                'weekly_plan_task_id' => $task['id'],
                'user_id' => self::USER_ID,
                'raw_material' => $materials[$index % count($materials)],
                'raw_lbs' => $rawLbs,
                'classified_lbs' => $classifiedLbs,
                'trimmed_lbs' => $trimmedLbs,
                'rejected_lbs' => $rejectedLbs,
                'packed_lbs' => $packedLbs,
                'recovery_pct' => $trimmedLbs / $rawLbs * 100,
                'rejection_pct' => $rejectedLbs / $rawLbs * 100,
                'observations' => $recoveryPct < 40 ? 'Baja recuperación por daño mecánico en la materia prima' : null,
                'extra_values' => null,
                'created_at' => $timestamps[$index],
                'updated_at' => $timestamps[$index],
            ];
        }

        return $records;
    }

    /**
     * Lot (GRN) records: applied raw material per lot, trimmed with the profile recovery and overripe losses.
     *
     * @param  array<string, mixed>  $task
     * @param  array<string, mixed>  $profile
     * @return list<array<string, mixed>>
     */
    private function buildLotRecords(array $task, float $targetPounds, array $profile, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $appliedTotal = $targetPounds / ($profile['value'] / 100);
        $shares = $this->splitShares((int) max(1, min(5, round($appliedTotal / 4000))));
        $lots = $this->lotCodes($task['line'], $task['operationDate'], 'GRN', count($shares));
        $timestamps = $this->spread($start, $end, count($shares));
        $records = [];

        foreach ($shares as $index => $share) {
            $appliedRawLbs = round($appliedTotal * $share, 2);
            $intakeLbs = round($appliedRawLbs / (mt_rand(1, 100) <= 40 ? 1 : $this->randomFloat(0.65, 0.98)), 2);
            $recoveryPct = $this->clamp($profile['value'] + $this->gaussian() * 3, 5, 95);
            $overripePct = $this->clamp($profile['secondary'] + $this->gaussian() * 1.5, 0, 100 - $recoveryPct);
            $trimmedLbs = round($appliedRawLbs * $recoveryPct / 100, 2);
            $overripeLbs = round($appliedRawLbs * $overripePct / 100, 2);

            $records[] = [
                'weekly_plan_task_id' => $task['id'],
                'user_id' => self::USER_ID,
                'entry_date' => $task['operationDate']->subDays(mt_rand(1, 5))->toDateString(),
                'lot' => $lots[$index],
                'recorded_at' => $timestamps[$index]->format('H:i:s'),
                'intake_lbs' => $intakeLbs,
                'applied_raw_lbs' => $appliedRawLbs,
                'trimmed_lbs' => $trimmedLbs,
                'overripe_lbs' => $overripeLbs,
                'recovery_pct' => $trimmedLbs / $appliedRawLbs * 100,
                'overripe_pct' => $overripeLbs / $appliedRawLbs * 100,
                'grn_balance' => round($intakeLbs - $appliedRawLbs, 2),
                'observations' => $overripePct > 12 ? 'Lote con alto porcentaje de sobremadurez' : null,
                'extra_values' => null,
                'created_at' => $timestamps[$index],
                'updated_at' => $timestamps[$index],
            ];
        }

        return $records;
    }

    /**
     * Null the capture columns whose field is not assigned to the line, as the capture services do.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function keepAssignedColumns(array $record, Line $line, string $family): array
    {
        $assignedKeys = $this->assignedKeys[$line->id] ?? [];

        if ($assignedKeys === []) {
            return $record;
        }

        foreach (self::CAPTURE_COLUMNS[$family] as $column) {
            if (! in_array($column, $assignedKeys, true)) {
                $record[$column] = null;
            }
        }

        return $record;
    }

    /**
     * Distinct lot codes like PA-26279-R5A / GRN-26279-R5A (year + day of year of reception + line suffix).
     *
     * @return list<string>
     */
    private function lotCodes(Line $line, CarbonImmutable $operationDate, string $prefix, int $count): array
    {
        $suffix = 'R'.substr($line->code, -2);
        $reception = $operationDate->subDays($prefix === 'PA' ? mt_rand(20, 45) : mt_rand(1, 5));
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = sprintf('%s-%s%03d-%s', $prefix, $reception->format('y'), $reception->dayOfYear, $suffix);
            $reception = $reception->subDays(mt_rand(1, 3));
        }

        return $codes;
    }

    /**
     * Raw material names for product records: catalog raw materials starting with the SKU's first word, or generic grades.
     *
     * @return list<string>
     */
    private function rawMaterialNamesFor(Sku $sku): array
    {
        if (isset($this->rawMaterialNames[$sku->id])) {
            return $this->rawMaterialNames[$sku->id];
        }

        $product = strtok((string) $sku->product_name, ' ') ?: 'MATERIA PRIMA';
        $names = RawMaterial::where('product_name', 'ilike', $product.'%')->orderBy('id')->limit(3)->pluck('product_name')->all();

        if ($names === []) {
            $names = ["{$product} GRADO A", "{$product} GRADO B", "{$product} PRODUCTOR EXTERNO"];
        }

        return $this->rawMaterialNames[$sku->id] = $names;
    }

    /**
     * Worse profiles get more (and longer) timeouts. An in-progress task may keep its last timeout open.
     *
     * @return list<array<string, mixed>>
     */
    private function buildTimeouts(string $family, CarbonImmutable $start, float $plannedHours, string $severity, bool $finished): array
    {
        $chance = match ($severity) {
            'critical' => 85,
            'moderate' => 55,
            default => 25,
        };

        if (mt_rand(1, 100) > $chance) {
            return [];
        }

        $timeoutIds = self::TIMEOUT_IDS[$family];
        $timeouts = [];
        $count = mt_rand(1, $severity === 'critical' ? 3 : 2);

        for ($i = 0; $i < $count; $i++) {
            $timeoutStart = $start->addMinutes(mt_rand(30, max(31, (int) ($plannedHours * 50))));
            $durationHours = round($this->randomFloat(0.25, 1.5), 2);
            $isOpen = ! $finished && $i === $count - 1 && mt_rand(1, 100) <= 40;

            if (! $finished && $timeoutStart->gt($this->now)) {
                continue;
            }

            $timeouts[] = [
                'timeout_id' => $timeoutIds[mt_rand(0, count($timeoutIds) - 1)],
                'user_id' => self::USER_ID,
                'start_date' => $timeoutStart,
                'end_date' => $isOpen ? null : $timeoutStart->addMinutes((int) round($durationHours * 60)),
                'duration_hours' => $isOpen ? null : $durationHours,
                'observation' => $isOpen ? 'En espera de mantenimiento' : null,
            ];
        }

        return $timeouts;
    }

    private function addObservations(int $taskId, string $family, string $severity, CarbonImmutable $at): void
    {
        $notes = self::TASK_OBSERVATIONS[$family][$severity] ?? [];

        if ($severity === 'warning' && mt_rand(1, 100) > 50) {
            $notes = [];
        }

        if ($notes === [] && $severity === 'ok' && mt_rand(1, 100) <= 20) {
            $notes = ['Operación sin novedades.'];
        }

        foreach ($notes as $index => $note) {
            DB::table('weekly_plan_task_observations')->insert([
                'weekly_plan_task_id' => $taskId,
                'user_id' => self::USER_ID,
                'observation' => $note,
                'created_at' => $at->subMinutes(10 * ($index + 1)),
                'updated_at' => $at->subMinutes(10 * ($index + 1)),
            ]);
        }

        $this->summary['observations'] += count($notes);
    }

    private function assignEmployees(int $taskId, Line $line): void
    {
        $crew = $this->crews[$line->id];
        $rows = array_map(fn (array $employee) => [...$employee, 'weekly_plan_task_id' => $taskId], array_slice($this->shuffle(collect($crew))->all(), 0, mt_rand(3, count($crew))));

        DB::table('weekly_plan_task_employees')->insert($rows);
        $this->summary['employees'] += count($rows);
    }

    /**
     * A stable crew of 4–7 employees per line, positions following the line code (e.g. 2REM5A001).
     *
     * @return list<array{code: string, name: string, position: string}>
     */
    private function buildCrew(Line $line): array
    {
        return array_map(fn (int $seat) => [
            'code' => 'LEG'.str_pad((string) $this->employeeSequence++, 5, '0', STR_PAD_LEFT),
            'name' => implode(' ', [
                self::FIRST_NAMES[mt_rand(0, count(self::FIRST_NAMES) - 1)],
                self::LAST_NAMES[mt_rand(0, count(self::LAST_NAMES) - 1)],
                self::LAST_NAMES[mt_rand(0, count(self::LAST_NAMES) - 1)],
            ]),
            'position' => $line->code.str_pad((string) $seat, 3, '0', STR_PAD_LEFT),
        ], range(1, mt_rand(4, 7)));
    }

    /**
     * Weighted pick of a family profile with a random value inside its ranges.
     *
     * @return array{label: string, severity: string, value: float, secondary: ?float}
     */
    private function pickProfile(string $family): array
    {
        $profiles = self::PROFILES[$family];
        $roll = mt_rand(1, array_sum(array_column($profiles, 'weight')));

        foreach ($profiles as $profile) {
            if (($roll -= $profile['weight']) <= 0) {
                break;
            }
        }

        return [
            'label' => $profile['label'],
            'severity' => $profile['severity'],
            'value' => $this->randomFloat(...$profile['range']),
            'secondary' => isset($profile['secondary']) ? $this->randomFloat(...$profile['secondary']) : null,
        ];
    }

    /**
     * Evenly spaced timestamps between start and end with a little jitter.
     *
     * @return list<CarbonImmutable>
     */
    private function spread(CarbonImmutable $start, CarbonImmutable $end, int $count): array
    {
        $minutesPerItem = max(5, $start->diffInMinutes($end) / max(1, $count));

        return array_map(fn (int $index) => $start->addMinutes(max(1, (int) round(($index + 1) * $minutesPerItem) - mt_rand(0, 4))), range(0, $count - 1));
    }

    /**
     * Random shares that add up to 1.
     *
     * @return list<float>
     */
    private function splitShares(int $count): array
    {
        $weights = array_map(fn () => $this->randomFloat(0.6, 1.4), range(1, $count));
        $total = array_sum($weights);

        return array_map(fn (float $weight) => $weight / $total, $weights);
    }

    /**
     * @template T
     *
     * @param  Collection<int, T>  $items
     * @return Collection<int, T>
     */
    private function shuffle(Collection $items): Collection
    {
        return $items->sortBy(fn () => mt_rand())->values();
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    private function randomFloat(float $min, float $max): float
    {
        return $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
    }

    /**
     * Standard normal sample (Box–Muller).
     */
    private function gaussian(): float
    {
        return sqrt(-2 * log(max(1e-10, mt_rand() / mt_getrandmax()))) * cos(2 * M_PI * (mt_rand() / mt_getrandmax()));
    }
}
