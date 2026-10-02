<?php

namespace App\Services\WeeklyPlanTasks;

use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Helpers\MailHandler;
use App\Interfaces\WeeklyPlanTasks\WeeklyPlanTasksServiceInterface;
use App\Mail\WeeklyPlanTasksOperationDateAssigned;
use App\Models\LineSku;
use App\Models\WeeklyPlanTask;
use App\Observers\WeeklyPlanTaskObserver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Override;

class WeeklyPlanTasksService implements WeeklyPlanTasksServiceInterface
{
    #[Override]
    public function createWeeklyPlanTask(array $data)
    {
        $performance = LineSku::find($data['line_sku_id'], ['*'])->load(['sku']);
        $sku = $performance->sku;
        $total_lbs = $data['boxes'] * $sku->presentation;

        $payload = [
            'boxes' => $data['boxes'],
            'pallets' => $data['boxes'] / $sku->boxes_per_pallet,
            'hours' => $total_lbs / $performance->lbs_performance,
            'destination' => $data['destination'],
            'operation_date' => $data['operation_date'],
            'weekly_plan_id' => $data['weekly_plan_id'],
            'line_sku_id' => $data['line_sku_id'],
        ];

        $weeklyPlanTask = WeeklyPlanTask::create($payload);

        return $weeklyPlanTask;
    }

    #[Override]
    public function getWeeklyPlanTasks(?string $limit, Request $request)
    {
        $query = WeeklyPlanTask::query();
        $query->with(['performance', 'performance.sku', 'performance.line']);

        if ($request->query('weeklyPlanId')) {
            $query->where('weekly_plan_id', $request->query('weeklyPlanId'));
        }

        if ($request->query('lineCode')) {
            $query->whereHas('performance', function ($p0) use ($request) {
                $p0->whereHas('line', function ($p1) use ($request) {
                    $p1->where('code', '=', $request->query('lineCode'));
                });
            });
        }

        if ($request->query('lineId')) {
            $query->whereHas('performance', function ($performance) use ($request) {
                $performance->where('line_id', $request->query('lineId'));
            });
        }

        if ($request->query('skuCode')) {
            $query->whereHas('performance.sku', function ($sku) use ($request) {
                $sku->where('code', 'LIKE', '%'.$request->query('skuCode').'%');
            });
        }

        if ($request->query('operationDate')) {
            $query->whereDate('operation_date', $request->query('operationDate'));
        }

        if ($request->query('noOperationDate')) {
            $query->whereNull('operation_date');
        }

        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->get();
    }

    #[Override]
    public function getWeeklyPlanTaskById(string $id)
    {
        $weeklyPlanTask = WeeklyPlanTask::find($id);
        if (! $weeklyPlanTask) {
            throw new NotFoundError('La tarea del plan semanal no existe');
        }

        return $weeklyPlanTask;
    }

    #[Override]
    public function updateWeeklyPlanTaskById(array $data, string $id)
    {
        $weeklyPlanTask = $this->getWeeklyPlanTaskById($id);
        $weeklyPlanTask->update($data);

        return true;
    }

    #[Override]
    public function deleteWeeklyPlanTaskById(string $id)
    {
        $weeklyPlanTask = $this->getWeeklyPlanTaskById($id);
        $weeklyPlanTask->delete();

        return true;
    }

    #[Override]
    public function assignOperationDate(array $tasksIds, string $operationDate)
    {
        $changedTasks = WeeklyPlanTaskObserver::withoutMail(function () use ($tasksIds, $operationDate) {
            return DB::transaction(function () use ($tasksIds, $operationDate) {
                $tasks = WeeklyPlanTask::with(['weeklyPlan', 'performance.sku', 'performance.line'])
                    ->whereIn('id', $tasksIds)
                    ->get();
                $changedTasks = [];

                foreach ($tasks as $task) {
                    $previousOperationDate = $task->operation_date;

                    $task->update(['operation_date' => $operationDate]);

                    if ($task->wasChanged('operation_date')) {
                        $weeklyPlan = $task->weeklyPlan;

                        $changedTasks[] = [
                            'id' => $task->id,
                            'oldOperationDate' => $previousOperationDate === null ? null : (string) $previousOperationDate,
                            'productName' => $task->performance?->sku?->product_name,
                            'lineName' => $task->performance?->line?->name,
                            'weeklyPlan' => $weeklyPlan ? "Semana {$weeklyPlan->week} · {$weeklyPlan->year}" : null,
                        ];
                    }
                }

                return $changedTasks;
            });
        });

        if ($changedTasks !== []) {
            MailHandler::notifyWeeklyPlanTaskRecipients(new WeeklyPlanTasksOperationDateAssigned(
                operationDate: $operationDate,
                userName: auth()->user()->name,
                changedAt: now(),
                tasks: $changedTasks,
            ));
        }

        return true;
    }

    /**
     * Split a weekly plan task into several tasks, one per date/boxes portion, then delete the original task.
     *
     * @param  array<int, array{boxes: int, operation_date: string}>  $portions
     */
    #[Override]
    public function splitWeeklyPlanTask(string $taskId, array $portions)
    {
        $task = $this->getWeeklyPlanTaskById($taskId);
        $task->load(['performance.sku']);

        if ($task->produced_boxes || $task->produced_pallets || $task->start_date || $task->status > 2) {
            throw new BadRequestError('No se puede dividir una tarea que ya inició o tiene producción registrada');
        }

        $portionsBoxes = array_sum(array_column($portions, 'boxes'));
        if ($portionsBoxes !== $task->boxes) {
            throw new BadRequestError("La suma de las cajas divididas ({$portionsBoxes}) no coincide con el total de la tarea ({$task->boxes})");
        }

        $performance = $task->performance;
        $sku = $performance->sku;

        $newTaskIds = DB::transaction(function () use ($task, $portions, $performance, $sku) {
            $newTaskIds = [];

            foreach ($portions as $portion) {
                $totalLbs = $portion['boxes'] * $sku->presentation;

                $newTask = WeeklyPlanTask::create([
                    'boxes' => $portion['boxes'],
                    'pallets' => $portion['boxes'] / $sku->boxes_per_pallet,
                    'hours' => $totalLbs / $performance->lbs_performance,
                    'destination' => $task->destination,
                    'operation_date' => $portion['operation_date'],
                    'weekly_plan_id' => $task->weekly_plan_id,
                    'line_sku_id' => $task->line_sku_id,
                    'status' => $task->status,
                ]);

                $newTaskIds[] = $newTask->id;
            }

            $task->delete();

            return $newTaskIds;
        });

        return WeeklyPlanTask::whereIn('id', $newTaskIds)->with(['performance.sku', 'performance.line'])->get();
    }

    /**
     * Get the packing material items related to the SKU of the given weekly plan task.
     */
    #[Override]
    public function getPackingMaterialItemsByTaskId(string $id)
    {
        $task = $this->getWeeklyPlanTaskById($id);
        $task->load(['performance.sku.packingMaterialItems.item']);

        if (! $task->performance?->sku) {
            throw new NotFoundError('La tarea del plan semanal no tiene un SKU asociado');
        }

        return $task;
    }

    /**
     * Start a task ready for execution: set its start date and move it from status 3 to 4.
     */
    #[Override]
    public function startWeeklyPlanTask(string $id)
    {
        return $this->transitionWeeklyPlanTask($id, 3, 4, fn () => [
            'start_date' => now(),
        ], 'Solo se puede iniciar una tarea lista para ejecución');
    }

    /**
     * End a task in progress: record its production, set its end date and move it from status 4 to 5.
     * Produced pallets are derived from the SKU boxes per pallet, or 0 when the SKU does not define it.
     *
     * @param  array{produced_boxes: int, weighed_pounds: float}  $data
     */
    #[Override]
    public function endWeeklyPlanTask(string $id, array $data)
    {
        return $this->transitionWeeklyPlanTask($id, 4, 5, function (WeeklyPlanTask $task) use ($data) {
            $boxesPerPallet = $task->performance?->sku?->boxes_per_pallet;

            return [
                'produced_boxes' => $data['produced_boxes'],
                'produced_pallets' => $boxesPerPallet ? $data['produced_boxes'] / $boxesPerPallet : 0,
                'weighed_pounds' => $data['weighed_pounds'],
                'end_date' => now(),
            ];
        }, 'Solo se puede finalizar una tarea en progreso');
    }

    /**
     * Move a task between statuses applying the given attributes, revalidating the status under a row lock.
     *
     * @param  callable(WeeklyPlanTask): array<string, mixed>  $attributes
     */
    private function transitionWeeklyPlanTask(string $id, int $fromStatus, int $toStatus, callable $attributes, string $errorMessage): WeeklyPlanTask
    {
        $task = $this->getWeeklyPlanTaskById($id);
        if ($task->status != $fromStatus) {
            throw new BadRequestError($errorMessage);
        }

        DB::transaction(function () use ($task, $fromStatus, $toStatus, $attributes, $errorMessage) {
            $lockedTask = WeeklyPlanTask::with('performance.sku')->lockForUpdate()->find($task->id);
            if ($lockedTask->status != $fromStatus) {
                throw new BadRequestError($errorMessage);
            }

            $lockedTask->forceFill([...$attributes($lockedTask), 'status' => $toStatus]);
            $lockedTask->save();
        });

        return $task->fresh(['performance.sku.client', 'performance.line']);
    }
}
