<?php

namespace Database\Seeders;

use App\Enums\CaptureType;
use App\Models\Line;
use App\Models\LineSku;
use App\Models\User;
use App\Models\WeeklyPlan;
use App\Models\WeeklyPlanTask;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * Finished tasks for the four weeks before the plan of the reference task, with their crew, capture records, timeouts
 * and observations. A line that already has tasks in a week is skipped, so it can be run more than once.
 *
 * Run: php artisan db:seed --class=WeeklyPlanTaskHistorySeeder
 */
class WeeklyPlanTaskHistorySeeder extends Seeder
{
    use WithoutModelEvents;

    private const REFERENCE_TASK_ID = 3;

    private const WEEKS_BACK = 4;

    /**
     * Operation days (offset from Monday) of each line's weekly tasks.
     *
     * @var array<string, list<int>>
     */
    private const LINE_DAYS = [
        '2REM5A' => [0, 2, 4],
        '2REM8A' => [1, 3, 5],
        'EJOTERA-1' => [0, 3],
        'EJOTERA-2' => [1, 4],
        'LDCFA' => [0, 2],
        'RFRESCO' => [2, 4],
    ];

    private const RECORD_TABLES = [
        'pallet' => 'weekly_plan_task_performance_records',
        'lot' => 'weekly_plan_task_lot_records',
        'product' => 'weekly_plan_task_product_records',
    ];

    private const TIMEOUT_NAMES = ['FALLAS EN MAQUINARIA', 'FALTA DE MATERIA PRIMA', 'DESVIACIONES DE CALIDAD'];

    private const OBSERVATIONS = [
        'pallet' => 'Se ajustó el llenado de cajas por variación de peso.',
        'lot' => 'Se separó producto sobremaduro y se notificó a calidad.',
        'product' => 'Se reforzó la supervisión del recorte para mejorar la recuperación.',
    ];

    private const DESTINATIONS = ['MIAMI', 'HOUSTON', 'PHILADELPHIA'];

    private const FIRST_NAMES = ['ANA', 'MARIA', 'JOSE', 'LUIS', 'SANDRA', 'CARLOS', 'ROSA', 'JUAN', 'MIRNA', 'PEDRO'];

    private const LAST_NAMES = ['LOPEZ', 'GARCIA', 'PEREZ', 'XOCOP', 'CHAVEZ', 'TOMAS', 'AJCOT', 'MORALES', 'COJTI'];

    /**
     * Crew size of the lines without positions.
     */
    private const DEFAULT_CREW_SIZE = 8;

    private Randomizer $random;

    private int $userId;

    private bool $hasWastePounds;

    private int $employeeSequence = 1;

    /**
     * @var Collection<int, int>
     */
    private Collection $timeoutIds;

    public function run(): void
    {
        $reference = WeeklyPlanTask::with('weeklyPlan')->find(self::REFERENCE_TASK_ID)
            ?? throw new RuntimeException('No existe la tarea de referencia; ejecute antes OperationSeeder.');

        $this->random = new Randomizer(new Mt19937(self::REFERENCE_TASK_ID));
        $this->userId = User::query()->orderBy('id')->firstOrFail()->id;
        $this->hasWastePounds = Schema::hasColumn('weekly_plan_tasks', 'waste_pounds');
        $this->timeoutIds = DB::table('timeouts')->whereIn('name', self::TIMEOUT_NAMES)->orderBy('id')->pluck('id');

        $referenceMonday = CarbonImmutable::now()
            ->setISODate($reference->weeklyPlan->year, $reference->weeklyPlan->week)
            ->startOfDay();

        $lines = Line::with(['lineFields.captureField', 'lineActivities', 'positions' => fn ($query) => $query->where('status', true)->orderBy('id')])
            ->whereIn('code', array_keys(self::LINE_DAYS))
            ->get()
            ->keyBy('code');

        DB::transaction(function () use ($lines, $reference, $referenceMonday): void {
            foreach (array_keys(self::LINE_DAYS) as $code) {
                if (! $lines->has($code)) {
                    $this->command?->warn("La línea {$code} no existe, se omite.");

                    continue;
                }

                $this->seedLine($lines[$code], $referenceMonday, $reference->line_sku_id);
            }
        });
    }

    private function seedLine(Line $line, CarbonImmutable $referenceMonday, int $referenceLineSkuId): void
    {
        $lineSkus = $this->lineSkus($line, $referenceLineSkuId);
        $crew = $this->buildCrew($line);

        if ($lineSkus->isEmpty()) {
            $this->command?->warn("La línea {$line->code} no tiene SKUs utilizables, se omite.");

            return;
        }

        $sequence = 0;

        foreach (range(self::WEEKS_BACK, 1) as $weeksBack) {
            $monday = $referenceMonday->subWeeks($weeksBack);
            $weeklyPlan = WeeklyPlan::firstOrCreate(['week' => $monday->isoWeek(), 'year' => $monday->isoWeekYear()]);

            $alreadySeeded = $weeklyPlan->tasks()
                ->whereHas('performance', fn ($query) => $query->where('line_id', $line->id))
                ->exists();

            if ($alreadySeeded) {
                continue;
            }

            foreach (self::LINE_DAYS[$line->code] as $dayOffset) {
                $lineSku = $lineSkus[$sequence % $lineSkus->count()];
                $this->createTask($weeklyPlan, $line, $lineSku, $monday->addDays($dayOffset), $crew, $sequence);
                $sequence++;
            }
        }
    }

    /**
     * Up to six active SKUs of the line, the reference one first. Pallet lines also need boxes per pallet.
     *
     * @return Collection<int, LineSku>
     */
    private function lineSkus(Line $line, int $referenceLineSkuId): Collection
    {
        return LineSku::with('sku')
            ->where('line_id', $line->id)
            ->where('status', true)
            ->where('lbs_performance', '>', 0)
            ->whereHas('sku', function ($query) use ($line): void {
                $query->where('presentation', '>', 0);

                if ($line->capture_type === CaptureType::Pallet) {
                    $query->where('boxes_per_pallet', '>', 0);
                }
            })
            ->orderBy('id')
            ->get()
            ->sortByDesc(fn (LineSku $lineSku): bool => $lineSku->id === $referenceLineSkuId)
            ->take(6)
            ->values();
    }

    /**
     * Create a finished task with everything captured during its execution.
     *
     * @param  list<array<string, mixed>>  $crew
     */
    private function createTask(WeeklyPlan $weeklyPlan, Line $line, LineSku $lineSku, CarbonImmutable $date, array $crew, int $sequence): void
    {
        $family = $line->capture_type->value;
        $sku = $lineSku->sku;
        $boxesPerPallet = (int) $sku->boxes_per_pallet;
        $targetPounds = $this->float(6, 10) * $lineSku->lbs_performance;

        $boxes = $line->capture_type === CaptureType::Pallet
            ? max(2, (int) floor($targetPounds / ($boxesPerPallet * $sku->presentation))) * $boxesPerPallet
            : max(1, (int) round($targetPounds / $sku->presentation));

        $hours = $boxes * $sku->presentation / $lineSku->lbs_performance;
        $start = $date->setTime(7, $this->random->getInt(0, 25));
        $workEnd = $start->addMinutes((int) round($hours * $this->float(0.95, 1.1) * 60));
        $timeout = $sequence % 3 === 1 ? $this->buildTimeout($start, $sequence) : null;
        $end = $workEnd->addMinutes((int) round(($timeout['duration_hours'] ?? 0) * 60));
        $plannedAt = $date->startOfWeek()->subDays(2)->setTime(15, 0);

        $records = match ($line->capture_type) {
            CaptureType::Pallet => $this->palletRecords($line, $boxes, $boxesPerPallet, (float) $sku->presentation, $start, $end),
            CaptureType::Lot => $this->lotRecords($line, $boxes * $sku->presentation, $start, $end),
            CaptureType::Product => $this->productRecords($sku->product_name, $boxes * $sku->presentation, $start, $end),
        };

        $weighedPounds = round(array_sum(array_column($records, $family === 'pallet' ? 'net_weight' : 'trimmed_lbs')), 2);
        $producedBoxes = $family === 'pallet'
            ? array_sum(array_column($records, 'boxes'))
            : (int) round($weighedPounds / $sku->presentation);

        $task = [
            'boxes' => $boxes,
            'produced_boxes' => $producedBoxes,
            'pallets' => $boxesPerPallet ? $boxes / $boxesPerPallet : 0,
            'produced_pallets' => $boxesPerPallet ? $producedBoxes / $boxesPerPallet : 0,
            'hours' => $hours,
            'weighed_pounds' => $weighedPounds,
            'destination' => self::DESTINATIONS[$sequence % count(self::DESTINATIONS)],
            'operation_date' => $date,
            'start_date' => $start,
            'end_date' => $end,
            'weekly_plan_id' => $weeklyPlan->id,
            'line_sku_id' => $lineSku->id,
            'status' => 5,
            'created_at' => $plannedAt,
            'updated_at' => $end,
        ];

        if ($this->hasWastePounds && $family === 'pallet') {
            $task['waste_pounds'] = round($weighedPounds * $this->float(0.002, 0.015), 2);
        }

        $taskId = DB::table('weekly_plan_tasks')->insertGetId($task);

        $this->insertRecords($taskId, $line, $records);
        $this->insertEmployees($taskId, $crew, $start);

        if ($timeout) {
            DB::table('weekly_plan_task_timeouts')->insert([
                ...$timeout,
                'weekly_plan_task_id' => $taskId,
                'created_at' => $timeout['start_date'],
                'updated_at' => $timeout['end_date'],
            ]);
        }

        if ($sequence % 2 === 0) {
            DB::table('weekly_plan_task_observations')->insert([
                'weekly_plan_task_id' => $taskId,
                'user_id' => $this->userId,
                'observation' => self::OBSERVATIONS[$family],
                'created_at' => $end,
                'updated_at' => $end,
            ]);
        }
    }

    /**
     * Full pallets with net weights close to the ticket weight, as in the reference task.
     *
     * @return list<array<string, mixed>>
     */
    private function palletRecords(Line $line, int $boxes, int $boxesPerPallet, float $presentation, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $plannedPallets = intdiv($boxes, $boxesPerPallet);
        $pallets = max(1, (int) round($plannedPallets * $this->float(0.92, 1)));
        $lot = sprintf('PA-%s%03d-R%s', $start->subDays(35)->format('y'), $start->subDays(35)->dayOfYear, substr($line->code, -2));
        $ticketWeight = round($boxesPerPallet * $presentation, 2);

        return array_map(function (int $number) use ($pallets, $boxesPerPallet, $lot, $ticketWeight, $start, $end): array {
            $recordedAt = $this->timestamp($start, $end, $number, $pallets);
            $netWeight = round($ticketWeight * (1 + $this->float(-0.004, 0.009)));
            $tare = $this->random->getInt(175, 190);

            return [
                'pallet_number' => $number,
                'lot' => $lot,
                'recorded_at' => $recordedAt->format('H:i:s'),
                'boxes' => $boxesPerPallet,
                'scale_weight' => $netWeight + $tare,
                'tare' => $tare,
                'net_weight' => $netWeight,
                'ticket_weight' => $ticketWeight,
                'difference' => round($netWeight - $ticketWeight, 2),
                'created_at' => $recordedAt,
                'updated_at' => $recordedAt,
            ];
        }, range(1, $pallets));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lotRecords(Line $line, float $pounds, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $count = $this->random->getInt(3, 5);

        return array_map(function (int $number) use ($line, $pounds, $count, $start, $end): array {
            $recordedAt = $this->timestamp($start, $end, $number, $count);
            $entryDate = $start->subDays($this->random->getInt(1, 3));
            $recovery = $this->float(0.78, 0.92);
            $appliedRawLbs = round($pounds / $count / 0.85, 2);
            $intakeLbs = round($appliedRawLbs * $this->float(1, 1.2), 2);
            $trimmedLbs = round($appliedRawLbs * $recovery, 2);
            $overripeLbs = round($appliedRawLbs * $this->float(0.02, 0.07), 2);

            return [
                'entry_date' => $entryDate->toDateString(),
                'lot' => sprintf('GRN-%s%03d-%s-%02d', $entryDate->format('y'), $entryDate->dayOfYear, substr($line->code, -1), $number),
                'recorded_at' => $recordedAt->format('H:i:s'),
                'intake_lbs' => $intakeLbs,
                'applied_raw_lbs' => $appliedRawLbs,
                'trimmed_lbs' => $trimmedLbs,
                'overripe_lbs' => $overripeLbs,
                'recovery_pct' => $trimmedLbs / $appliedRawLbs * 100,
                'overripe_pct' => $overripeLbs / $appliedRawLbs * 100,
                'grn_balance' => round($intakeLbs - $appliedRawLbs, 2),
                'observations' => $recovery < 0.8 ? 'Lote con recuperación por debajo del estándar' : null,
                'created_at' => $recordedAt,
                'updated_at' => $recordedAt,
            ];
        }, range(1, $count));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function productRecords(string $productName, float $pounds, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $count = $this->random->getInt(3, 5);

        return array_map(function (int $number) use ($productName, $pounds, $count, $start, $end): array {
            $recordedAt = $this->timestamp($start, $end, $number, $count);
            $recovery = $this->float(0.62, 0.8);
            $rawLbs = round($pounds / $count / 0.7, 2);
            $trimmedLbs = round($rawLbs * $recovery, 2);
            $rejectedLbs = round($rawLbs * $this->float(0.03, 0.1), 2);

            return [
                'raw_material' => strtok($productName, ' ').' LOTE '.$number,
                'raw_lbs' => $rawLbs,
                'classified_lbs' => round($rawLbs - $rejectedLbs, 2),
                'trimmed_lbs' => $trimmedLbs,
                'rejected_lbs' => $rejectedLbs,
                'packed_lbs' => round($trimmedLbs * $this->float(0.96, 1), 2),
                'recovery_pct' => $trimmedLbs / $rawLbs * 100,
                'rejection_pct' => $rejectedLbs / $rawLbs * 100,
                'observations' => $recovery < 0.65 ? 'Materia prima con recuperación baja' : null,
                'created_at' => $recordedAt,
                'updated_at' => $recordedAt,
            ];
        }, range(1, $count));
    }

    /**
     * Insert the capture records keeping only the fields assigned to the line, as the capture services do.
     *
     * @param  list<array<string, mixed>>  $records
     */
    private function insertRecords(int $taskId, Line $line, array $records): void
    {
        $assignedKeys = $line->lineFields->pluck('captureField.key')->all();
        $columns = array_flip([...$assignedKeys, 'created_at', 'updated_at']);

        DB::table(self::RECORD_TABLES[$line->capture_type->value])->insert(array_map(fn (array $record): array => [
            ...array_intersect_key($record, $columns),
            'weekly_plan_task_id' => $taskId,
            'user_id' => $this->userId,
        ], $records));
    }

    /**
     * @param  list<array<string, mixed>>  $crew
     */
    private function insertEmployees(int $taskId, array $crew, CarbonImmutable $start): void
    {
        $confirmedAt = $start->subMinutes(15);

        DB::table('weekly_plan_task_employees')->insert(array_map(fn (array $employee): array => [
            ...$employee,
            'weekly_plan_task_id' => $taskId,
            'created_at' => $confirmedAt,
            'updated_at' => $confirmedAt,
        ], $crew));
    }

    /**
     * Stable crew of the line: one employee per position within its activity quota. Wildcard lines share their first
     * position and fill their activity quotas; lines without positions get employees without assignment.
     *
     * @return list<array{code: string, name: string, position: ?string, position_id: ?int, line_activity_id: ?int}>
     */
    private function buildCrew(Line $line): array
    {
        $firstPosition = $line->positions->first();

        if (! $firstPosition) {
            $this->command?->warn("La línea {$line->code} no tiene posiciones, sus empleados quedan sin posición ni actividad.");

            return array_map(fn (): array => $this->employee(null, null, null), range(1, self::DEFAULT_CREW_SIZE));
        }

        $crew = [];

        if ($line->is_wildcard) {
            foreach ($line->lineActivities as $lineActivity) {
                for ($seat = 0; $seat < $lineActivity->quantity; $seat++) {
                    $crew[] = $this->employee($firstPosition->code, $firstPosition->id, $lineActivity->id);
                }
            }

            return $crew;
        }

        $lineActivities = $line->lineActivities->keyBy('activity_id');

        foreach ($line->positions->groupBy('activity_id') as $activityId => $positions) {
            $lineActivity = $lineActivities->get($activityId);

            foreach ($positions->take($lineActivity?->quantity ?? 0) as $position) {
                $crew[] = $this->employee($position->code, $position->id, $lineActivity->id);
            }
        }

        return $crew;
    }

    /**
     * @return array{code: string, name: string, position: ?string, position_id: ?int, line_activity_id: ?int}
     */
    private function employee(?string $position, ?int $positionId, ?int $lineActivityId): array
    {
        $number = $this->employeeSequence++;

        return [
            'code' => sprintf('DEM%05d', $number),
            'name' => implode(' ', [
                self::FIRST_NAMES[$number % count(self::FIRST_NAMES)],
                self::LAST_NAMES[$number % count(self::LAST_NAMES)],
                self::LAST_NAMES[intdiv($number, count(self::LAST_NAMES)) % count(self::LAST_NAMES)],
            ]),
            'position' => $position,
            'position_id' => $positionId,
            'line_activity_id' => $lineActivityId,
        ];
    }

    /**
     * @return array{timeout_id: int, user_id: int, start_date: CarbonImmutable, end_date: CarbonImmutable, duration_hours: float, observation: ?string}|null
     */
    private function buildTimeout(CarbonImmutable $start, int $sequence): ?array
    {
        if ($this->timeoutIds->isEmpty()) {
            return null;
        }

        $minutes = $this->random->getInt(1, 4) * 15;
        $startDate = $start->addMinutes($this->random->getInt(60, 180));

        return [
            'timeout_id' => $this->timeoutIds[intdiv($sequence, 3) % $this->timeoutIds->count()],
            'user_id' => $this->userId,
            'start_date' => $startDate,
            'end_date' => $startDate->addMinutes($minutes),
            'duration_hours' => $minutes / 60,
            'observation' => null,
        ];
    }

    private function timestamp(CarbonImmutable $start, CarbonImmutable $end, int $number, int $count): CarbonImmutable
    {
        return $start->addMinutes((int) floor($start->diffInMinutes($end) * $number / $count));
    }

    private function float(float $min, float $max): float
    {
        return $this->random->getFloat($min, $max);
    }
}
