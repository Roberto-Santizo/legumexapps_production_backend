<?php

namespace Database\Seeders;

use App\Enums\CaptureType;
use App\Models\Line;
use App\Models\LineSku;
use App\Models\WeeklyPlan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Weekly plan of the current week with tasks in status 2: packing material delivered, waiting for their crew to be
 * confirmed. Nothing else is created (no employees, records, timeouts or observations). A line that already has tasks
 * in the plan is skipped, so it can be run more than once.
 *
 * Requires the catalog to be seeded first.
 */
class OperationSeeder extends Seeder
{
    private const RANDOM_SEED = 48;

    private const STATUS_AWAITING_EMPLOYEES = 2;

    /**
     * Operation days (offset from Monday) of each line's tasks.
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

    private const MAX_SKUS_PER_LINE = 6;

    private const DESTINATIONS = ['MIAMI', 'HOUSTON', 'PHILADELPHIA'];

    private Randomizer $random;

    public function run(): void
    {
        $this->random = new Randomizer(new Mt19937(self::RANDOM_SEED));

        $monday = CarbonImmutable::now()->startOfWeek();
        $weeklyPlan = WeeklyPlan::firstOrCreate(['week' => $monday->isoWeek(), 'year' => $monday->isoWeekYear()]);

        $lines = Line::whereIn('code', array_keys(self::LINE_DAYS))->get()->keyBy('code');

        DB::transaction(function () use ($lines, $weeklyPlan, $monday): void {
            $sequence = 0;

            foreach (self::LINE_DAYS as $code => $dayOffsets) {
                $line = $lines->get($code);

                if (! $line) {
                    $this->command?->warn("La línea {$code} no existe, se omite.");

                    continue;
                }

                $sequence = $this->seedLine($weeklyPlan, $line, $monday, $dayOffsets, $sequence);
            }
        });
    }

    /**
     * @param  list<int>  $dayOffsets
     */
    private function seedLine(WeeklyPlan $weeklyPlan, Line $line, CarbonImmutable $monday, array $dayOffsets, int $sequence): int
    {
        $alreadySeeded = $weeklyPlan->tasks()
            ->whereHas('performance', fn ($query) => $query->where('line_id', $line->id))
            ->exists();

        if ($alreadySeeded) {
            return $sequence;
        }

        $lineSkus = $this->lineSkus($line);

        if ($lineSkus->isEmpty()) {
            $this->command?->warn("La línea {$line->code} no tiene SKUs utilizables, se omite.");

            return $sequence;
        }

        foreach ($dayOffsets as $index => $dayOffset) {
            $this->createTask($weeklyPlan, $line, $lineSkus[$index % $lineSkus->count()], $monday->addDays($dayOffset), $sequence);
            $sequence++;
        }

        return $sequence;
    }

    /**
     * Active SKUs of the line able to size a task, those with activity quotas first. Pallet lines also need boxes per pallet.
     *
     * @return Collection<int, LineSku>
     */
    private function lineSkus(Line $line): Collection
    {
        return LineSku::with('sku')
            ->withCount('lineSkuActivities')
            ->where('line_id', $line->id)
            ->where('status', true)
            ->where('lbs_performance', '>', 0)
            ->whereHas('sku', function ($query) use ($line): void {
                $query->where('presentation', '>', 0);

                if ($line->capture_type === CaptureType::Pallet) {
                    $query->where('boxes_per_pallet', '>', 0);
                }
            })
            ->orderByDesc('line_sku_activities_count')
            ->orderBy('id')
            ->limit(self::MAX_SKUS_PER_LINE)
            ->get();
    }

    /**
     * Task sized for a 6–10 hour shift, computed as WeeklyPlanTasksService::createWeeklyPlanTask does.
     */
    private function createTask(WeeklyPlan $weeklyPlan, Line $line, LineSku $lineSku, CarbonImmutable $operationDate, int $sequence): void
    {
        $sku = $lineSku->sku;
        $boxesPerPallet = (int) $sku->boxes_per_pallet;
        $targetPounds = $this->random->getFloat(6, 10) * $lineSku->lbs_performance;

        $boxes = $line->capture_type === CaptureType::Pallet
            ? max(1, (int) floor($targetPounds / ($boxesPerPallet * $sku->presentation))) * $boxesPerPallet
            : max(1, (int) round($targetPounds / $sku->presentation));

        $plannedAt = $operationDate->startOfWeek()->subDays(2)->setTime(15, 0);

        DB::table('weekly_plan_tasks')->insert([
            'boxes' => $boxes,
            'pallets' => $boxesPerPallet ? $boxes / $boxesPerPallet : 0,
            'hours' => $boxes * $sku->presentation / $lineSku->lbs_performance,
            'destination' => self::DESTINATIONS[$sequence % count(self::DESTINATIONS)],
            'operation_date' => $operationDate,
            'weekly_plan_id' => $weeklyPlan->id,
            'line_sku_id' => $lineSku->id,
            'status' => self::STATUS_AWAITING_EMPLOYEES,
            'created_at' => $plannedAt,
            'updated_at' => $plannedAt,
        ]);
    }
}
