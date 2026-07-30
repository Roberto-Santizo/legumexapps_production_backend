<?php

namespace App\Actions\DraftWeeklyPlans;

use App\Errors\BadRequestError;
use App\Models\DraftWeeklyPlan;
use App\Models\DraftWeeklyPlanTask;
use App\Models\LineSku;
use App\Models\WeeklyPlan;
use App\Models\WeeklyPlanTask;
use Illuminate\Support\Facades\DB;

class ConfirmDraftWeeklyPlan
{
    public function exec(DraftWeeklyPlan $draftWeeklyPlan): void
    {
        $tasks = $draftWeeklyPlan->tasks()->with('sku')->get();

        $tasksWithoutLine = $tasks->whereNull('line_id');
        if ($tasksWithoutLine->isNotEmpty()) {
            throw new BadRequestError("Algunas tareas no tienen línea asignada");
        }

        $performances = LineSku::query()->whereIn('line_id', $tasks->pluck('line_id')->unique())->whereIn('sku_id', $tasks->pluck('sku_id')->unique())->get()
            ->keyBy(fn (LineSku $performance) => "{$performance->line_id}-{$performance->sku_id}");

        $tasksWithoutPerformance = $tasks->reject(
            fn (DraftWeeklyPlanTask $task) => $performances->has("{$task->line_id}-{$task->sku_id}")
        );

        if ($tasksWithoutPerformance->isNotEmpty()) {
            $taskIds = $tasksWithoutPerformance->pluck('id')->implode(', ');
            throw new BadRequestError("No existe un rendimiento configurado para la línea y el SKU de las siguientes tareas: {$taskIds}");
        }

        Db::transaction(function () use ($draftWeeklyPlan, $tasks, $performances): void {
            $plan = WeeklyPlan::create(['week' => $draftWeeklyPlan->week, 'year' => $draftWeeklyPlan->year]);

            foreach ($tasks as $task) {
                $performance = $performances->get("{$task->line_id}-{$task->sku_id}");
                $totalPounds = $task->boxes * $task->sku->presentation;

                WeeklyPlanTask::create([
                    'boxes' => $task->boxes,
                    'pallets' => $task->boxes / $task->sku->boxes_per_pallet,
                    'hours' => $totalPounds / $performance->lbs_performance,
                    'destination' => $task->destination,
                    'operation_date' => $task->operation_date,
                    'weekly_plan_id' => $plan->id,
                    'line_sku_id' => $performance->id,
                ]);
            }

            $draftWeeklyPlan->forceFill(['confirmation_date' => now()])->save();
        });
    }
}